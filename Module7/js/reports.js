// Reports page.
// Pick a report and it loads straight away. "Items won by bidder" waits for a
// paddle number and "Items donated by donor" waits for a donor. The running
// list of winners refreshes itself every few seconds and can auto-scroll, so it
// can sit on a screen during the auction.

document.addEventListener("DOMContentLoaded", function () {
    const reportSel = document.getElementById("report");
    const paddleBox = document.getElementById("param-paddle");
    const paddleField = document.getElementById("p_paddle");
    const donorBox = document.getElementById("param-donor");
    const donorSel = document.getElementById("p_donor");
    const scrollBox = document.getElementById("param-scroll");
    const scrollChk = document.getElementById("autoscroll");
    const feedBox = document.getElementById("param-feed");
    const printBtn = document.getElementById("print-btn");
    const statusEl = document.getElementById("report-status");
    const output = document.getElementById("report-output");

    const REFRESH_MS = 5000;
    const BLANK_BID_LINES = 15;     // empty rows on each printable bid sheet

    let latestRequest = 0;      // lets us ignore a slow, out-of-date reply
    let refreshTimer = null;
    let scrollTimer = null;
    let lastShown = "";         // what is on screen, so an unchanged refresh doesn't redraw

    // ---- wiring -----------------------------------------------------------

    document.getElementById("report-controls").addEventListener("submit", function (e) {
        e.preventDefault();
    });

    reportSel.addEventListener("change", onReportChange);
    donorSel.addEventListener("change", function () { run(false); });
    paddleField.addEventListener("blur", function () { run(false); });
    paddleField.addEventListener("keydown", function (e) {
        if (e.key === "Enter") {
            e.preventDefault();
            run(false);
        }
    });
    printBtn.addEventListener("click", function () { window.print(); });
    scrollChk.addEventListener("change", function () {
        if (scrollChk.checked) { startAutoScroll(); } else { stopAutoScroll(); }
    });

    loadDonors();

    // ---- helpers ----------------------------------------------------------

    function setStatus(message) {
        if (statusEl.textContent !== message) {   // don't re-announce an identical message
            statusEl.textContent = message;
        }
    }

    function clearOutput() {
        output.textContent = "";
        lastShown = "";
        printBtn.disabled = true;
    }

    function stopRefresh() {
        if (refreshTimer !== null) {
            clearInterval(refreshTimer);
            refreshTimer = null;
        }
    }

    function stopAutoScroll() {
        if (scrollTimer !== null) {
            clearInterval(scrollTimer);
            scrollTimer = null;
        }
    }

    function el(tag, text, className) {
        const node = document.createElement(tag);
        if (text !== undefined && text !== null) {
            node.textContent = text;    // textContent, not innerHTML -- names are user-entered
        }
        if (className) {
            node.className = className;
        }
        return node;
    }

    // ---- choosing a report ----------------------------------------------------

    function onReportChange() {
        const report = reportSel.value;

        stopRefresh();
        stopAutoScroll();
        scrollChk.checked = false;
        latestRequest++;             // anything still loading for the previous report is now stale
        clearOutput();

        paddleBox.hidden = (report !== "won-by-bidder");
        donorBox.hidden = (report !== "by-donor");
        scrollBox.hidden = (report !== "winners-running");
        feedBox.hidden = (report !== "winners-running");

        if (report === "") {
            setStatus("Choose a report.");
            return;
        }
        run(false);
    }

    // silent = a background refresh: don't flash "Loading...".
    function run(silent) {
        const report = reportSel.value;
        if (report === "") {
            return;
        }

        const params = new URLSearchParams({ report: report });

        if (report === "won-by-bidder") {
            const paddle = paddleField.value.trim();
            if (paddle === "") {
                clearOutput();
                setStatus("Enter a paddle number.");
                return;
            }
            if (!/^\d{1,3}$/.test(paddle) || Number(paddle) < 1) {
                clearOutput();
                setStatus("Paddle number must be a whole number from 1 to 999");
                return;
            }
            params.set("paddle_number", paddle);
        }
        if (report === "by-donor") {
            if (donorSel.value === "") {
                clearOutput();
                setStatus("Choose a donor.");
                return;
            }
            params.set("donor_id", donorSel.value);
        }

        const mine = ++latestRequest;
        if (!silent) {
            setStatus("Loading...");
        }

        fetch("api/report.php?" + params.toString())
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (mine !== latestRequest) {
                    return; // the clerk picked something else while this was loading
                }
                if (!result.success) {
                    stopRefresh();
                    clearOutput();
                    setStatus(result.error);
                    return;
                }
                show(result);
            })
            .catch(function (err) {
                if (mine !== latestRequest) {
                    return;
                }
                if (!silent) {
                    clearOutput();
                }
                setStatus("Could not load the report -- check your connection.");
                console.error(err);
            });
    }

    // ---- drawing the result ---------------------------------------------------

    function show(result) {
        const snapshot = JSON.stringify(result);
        const changed = (snapshot !== lastShown);

        if (changed) {
            lastShown = snapshot;
            output.textContent = "";
            // Bid sheets print one per page with no title block of their own (see reports.css).
            output.className = (result.layout === "sheets") ? "sheets-layout" : "";
            output.appendChild(el("h2", result.title));
            output.appendChild(el("p", "Printed " + new Date().toLocaleString(), "printed"));

            if (result.layout === "sheets") {
                drawSheets(result);
            } else {
                drawTable(result);
            }
        }

        if (result.layout === "sheets") {
            const n = result.sheets.length;
            setStatus(n === 0 ? "No items to print."
                              : n + " bid sheet" + (n === 1 ? "" : "s") + ". Use Print to print one per page.");
        } else {
            const n = result.rows.length;
            let message = n + " row" + (n === 1 ? "" : "s") + ".";
            if (result.refresh) {
                message += " Refreshes every " + (REFRESH_MS / 1000) + " seconds.";
            }
            setStatus(message);
        }

        printBtn.disabled = false;

        // The running list keeps itself up to date.
        if (result.refresh && refreshTimer === null) {
            refreshTimer = setInterval(function () { run(true); }, REFRESH_MS);
        }
    }

    function drawTable(result) {
        if (result.rows.length === 0) {
            output.appendChild(el("p", "Nothing to show for this report."));
        } else {
            const table = el("table");
            const headRow = el("tr");
            result.columns.forEach(function (label, i) {
                headRow.appendChild(el("th", label, result.numeric.indexOf(i) !== -1 ? "num" : ""));
            });
            table.appendChild(el("thead")).appendChild(headRow);

            const body = el("tbody");
            result.rows.forEach(function (cells) {
                const tr = el("tr");
                cells.forEach(function (text, i) {
                    tr.appendChild(el("td", text, result.numeric.indexOf(i) !== -1 ? "num" : ""));
                });
                body.appendChild(tr);
            });
            table.appendChild(body);
            output.appendChild(table);
        }

        if (result.summary.length > 0) {
            const summary = el("table", null, "summary");
            result.summary.forEach(function (pair) {
                const tr = el("tr");
                tr.appendChild(el("th", pair[0]));
                tr.appendChild(el("td", pair[1]));
                summary.appendChild(tr);
            });
            output.appendChild(summary);
        }
    }

    function drawSheets(result) {
        if (result.sheets.length === 0) {
            output.appendChild(el("p", "No items to print."));
            return;
        }

        result.sheets.forEach(function (sheet) {
            const card = el("div", null, "bid-sheet");
            // The section is what the item is labeled with and found by, so it leads the sheet.
            card.appendChild(el("p", sheet.section === "(none)" ? "No section" : "Section " + sheet.section, "section-no"));
            card.appendChild(el("h2", sheet.name));
            if (sheet.donor) {
                card.appendChild(el("p", "Donated by: " + sheet.donor, "meta"));
            }
            if (sheet.description) {
                card.appendChild(el("p", sheet.description, "meta"));
            }
            if (sheet.start) {
                card.appendChild(el("p", "Starting bid: " + sheet.start, "start"));
            }

            const lines = el("table", null, "bid-lines");
            const headRow = el("tr");
            ["Paddle #", "Bid Amount"].forEach(function (label) {
                headRow.appendChild(el("th", label));
            });
            lines.appendChild(el("thead")).appendChild(headRow);

            const body = el("tbody");
            for (let i = 0; i < BLANK_BID_LINES; i++) {
                const tr = el("tr");
                tr.appendChild(el("td"));
                tr.appendChild(el("td"));
                body.appendChild(tr);
            }
            lines.appendChild(body);
            card.appendChild(lines);

            output.appendChild(card);
        });
    }

    // ---- auto-scroll for the running winners list ----------------------------------

    function startAutoScroll() {
        stopAutoScroll();
        let waitingAtBottom = false;
        let pauseUntil = 0;

        scrollTimer = setInterval(function () {
            const now = Date.now();
            if (now < pauseUntil) {
                return;
            }
            const maxY = document.documentElement.scrollHeight - window.innerHeight;
            if (window.scrollY >= maxY - 1) {
                // Reached the end: rest a moment, then jump back to the top.
                if (!waitingAtBottom) {
                    waitingAtBottom = true;
                    pauseUntil = now + 2500;
                } else {
                    waitingAtBottom = false;
                    window.scrollTo(0, 0);
                    pauseUntil = now + 2500;
                }
                return;
            }
            window.scrollBy(0, 1);
        }, 30);
    }

    // ---- dropdown contents -----------------------------------------------------------

    function loadDonors() {
        fetch("api/get_donors.php")
            .then(function (response) { return response.json(); })
            .then(function (result) {
                donorSel.textContent = "";
                const first = document.createElement("option");
                first.value = "";
                first.textContent = result.success ? "-- Choose a donor --" : "-- Could not load donors --";
                donorSel.appendChild(first);
                if (result.success) {
                    result.donors.forEach(function (donor) {
                        const opt = document.createElement("option");
                        opt.value = donor.donor_id;
                        opt.textContent = donor.name;
                        donorSel.appendChild(opt);
                    });
                }
            })
            .catch(function (err) { console.error(err); });
    }
});
