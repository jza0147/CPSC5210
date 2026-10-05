// Projector display for the winners.
//   - the newest winner is shown big in the middle and flashes when a new one arrives
//   - everything before it scrolls right to left along the bottom
// It checks the server every few seconds and needs no one to touch it.
// Add ?names=1 to the page address to show the winner's name as well.

document.addEventListener("DOMContentLoaded", function () {
    const POLL_MS = 3000;
    const TICKER_PX_PER_SECOND = 110;     // scroll speed (scaled up a little on big screens)

    const showNames = new URLSearchParams(window.location.search).get("names") === "1";
    const feedUrl = "api/get_feed.php" + (showNames ? "?names=1" : "");

    const stage = document.getElementById("stage");
    const waiting = document.getElementById("waiting");
    const winnerBox = document.getElementById("winner");
    const totalsEl = document.getElementById("totals");
    const lostEl = document.getElementById("link-lost");
    const track = document.getElementById("track");
    const ticker = document.getElementById("ticker");
    const hint = document.getElementById("hint");

    let firstLoad = true;
    let shownKey = null;        // the winner currently on the big display
    let tickerSignature = null; // what the ticker was built from, so it only rebuilds on a change
    let lastResult = null;      // the last good reply from the server

    // ---- helpers ----------------------------------------------------------

    // Everything from the database goes in with textContent, never innerHTML.
    function span(className, text) {
        const node = document.createElement("span");
        if (className) { node.className = className; }
        node.textContent = text;
        return node;
    }

    function setText(id, text) {
        document.getElementById(id).textContent = text;
    }

    // ---- making long names and big amounts fit ---------------------------------------------

    // The sizes in the stylesheet suit normal names. A very long item name or a huge amount
    // is shrunk, a little at a time, until everything fits inside the middle of the screen.
    const labelEl = document.getElementById("stage-label");
    const sectionEl = document.getElementById("w-section-row");
    const amountEl = document.getElementById("w-amount");

    function fitStage() {
        if (winnerBox.hidden) {
            return;
        }
        sectionEl.style.fontSize = "";
        amountEl.style.fontSize = "";

        function shrink(el, tooBig) {
            let size = parseFloat(getComputedStyle(el).fontSize);
            let guard = 40;
            while (tooBig() && size > 14 && guard-- > 0) {
                size *= 0.93;
                el.style.fontSize = size + "px";
            }
        }
        const availableWidth = stage.clientWidth * 0.94;
        const contentHeight = function () {
            const lastEl = document.getElementById("w-name").textContent !== "" ? document.getElementById("w-name") : amountEl;
            // offsetTop/offsetHeight ignore the pop-in animation's scaling; getBoundingClientRect would not.
            return (lastEl.offsetTop + lastEl.offsetHeight) - labelEl.offsetTop;
        };

        shrink(amountEl, function () { return amountEl.scrollWidth > availableWidth; });
        shrink(sectionEl, function () { return contentHeight() > stage.clientHeight * 0.96 || sectionEl.scrollWidth > availableWidth; });
    }

    // ---- the big display -----------------------------------------------------------

    function showLatest(latest) {
        if (latest === null) {
            winnerBox.hidden = true;
            waiting.hidden = false;
            shownKey = null;
            return;
        }
        waiting.hidden = true;
        winnerBox.hidden = false;
        document.getElementById("stage-label").textContent = "Latest winner";

        setText("w-section", latest.section !== "" ? latest.section : "\u2014");
        setText("w-paddle", latest.paddle);
        setText("w-name", showNames && latest.winner ? latest.winner : "");
        setText("w-amount", latest.amount);

        // Flash for a new winner, but not just because the page was opened or reloaded.
        if (!firstLoad && latest.key !== shownKey) {
            stage.classList.remove("flash");
            void stage.offsetWidth;          // restart the animation if one is still running
            stage.classList.add("flash");
        }
        shownKey = latest.key;
        fitStage();
    }

    // ---- the ticker ------------------------------------------------------------------

    function buildGroup(recent) {
        const group = document.createElement("div");
        group.style.display = "flex";
        recent.forEach(function (w, index) {
            const item = document.createElement("span");
            item.className = "t-item";
            item.appendChild(span("t-dim", "Section "));
            item.appendChild(document.createTextNode(w.section !== "" ? w.section : "\u2014"));
            item.appendChild(span("t-dim", "  ·  Paddle "));
            item.appendChild(document.createTextNode(w.paddle));
            if (showNames && w.winner) {
                item.appendChild(span("t-dim", "  " + w.winner));
            }
            item.appendChild(span("t-dim", "  ·  "));
            item.appendChild(span("t-amt", w.amount));
            group.appendChild(item);
            group.appendChild(span("t-sep", "◆"));   // a diamond between winners
        });
        return group;
    }

    function drawTicker(recent) {
        const signature = JSON.stringify(recent.map(function (w) { return w.key; }));
        if (signature === tickerSignature) {
            return;     // nothing new: leave the scroll running smoothly
        }
        tickerSignature = signature;

        track.classList.remove("moving");
        track.textContent = "";

        if (recent.length === 0) {
            track.appendChild(span("t-note", "Earlier winners will scroll by here."));
            return;
        }

        // Repeat the winners until they are wider than the screen, then use two identical
        // halves so the scroll loops with no gap.
        const base = buildGroup(recent);
        track.appendChild(base);
        let copies = 1;
        while (track.scrollWidth < window.innerWidth * 1.2 && copies < 40) {
            track.appendChild(base.cloneNode(true));
            copies++;
        }
        const halfWidth = track.scrollWidth;
        const half = Array.from(track.children);
        half.forEach(function (node) { track.appendChild(node.cloneNode(true)); });

        const speed = TICKER_PX_PER_SECOND * Math.max(1, window.innerWidth / 1280);
        track.style.setProperty("--ticker-seconds", (halfWidth / speed).toFixed(1) + "s");
        void track.offsetWidth;
        track.classList.add("moving");
    }

    // ---- polling -----------------------------------------------------------------------

    function apply(result) {
        totalsEl.textContent = "";
        if (result.sold > 0) {
            totalsEl.appendChild(document.createTextNode(result.sold + (result.sold === 1 ? " item sold · " : " items sold · ")));
            const total = document.createElement("strong");
            total.textContent = result.total + " raised";
            totalsEl.appendChild(total);
        }
        showLatest(result.latest);
        drawTicker(result.recent);
        firstLoad = false;
        lastResult = result;
    }

    function poll() {
        fetch(feedUrl, { cache: "no-store" })
            .then(function (response) { return response.json(); })
            .then(function (result) {
                if (!result.success) {
                    throw new Error(result.error || "Server error");
                }
                lostEl.hidden = true;
                apply(result);
            })
            .catch(function (err) {
                console.error(err);
                lostEl.hidden = false;      // keep showing the last good display
            })
            .then(function () {
                setTimeout(poll, POLL_MS);  // the next check starts after this one finishes
            });
    }

    // ---- full screen, hidden mouse pointer ------------------------------------------------------

    function toggleFullscreen() {
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else if (document.documentElement.requestFullscreen) {
            document.documentElement.requestFullscreen();
        }
    }
    document.addEventListener("keydown", function (e) {
        if (e.key === "f" || e.key === "F") {
            toggleFullscreen();
        }
    });
    document.addEventListener("dblclick", toggleFullscreen);

    let idleTimer = null;
    function wake() {
        document.body.classList.remove("idle");
        clearTimeout(idleTimer);
        idleTimer = setTimeout(function () { document.body.classList.add("idle"); }, 3000);
    }
    document.addEventListener("mousemove", wake);
    wake();
    setTimeout(function () { hint.classList.add("gone"); }, 8000);

    // A resized window (or one dragged onto the projector) needs the ticker measured again.
    let resizeTimer = null;
    window.addEventListener("resize", function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            fitStage();
            if (lastResult !== null) {
                tickerSignature = null;
                drawTicker(lastResult.recent);
            }
        }, 400);
    });

    poll();
});
