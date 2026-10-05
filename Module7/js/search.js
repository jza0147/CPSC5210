// Search page.
// Searches as you type (after a short pause) and whenever you switch what you
// are searching for. Each result has a link that opens the matching entry page
// with that record loaded for editing.

document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("search-form");
    const queryField = document.getElementById("q");
    const hint = document.getElementById("search-hint");
    const status = document.getElementById("search-status");
    const table = document.getElementById("results");
    const head = document.getElementById("results-head");
    const body = document.getElementById("results-body");
    const typeRadios = form.querySelectorAll('input[name="type"]');

    const HINTS = {
        items:   "Matches section, name, description or donor name.",
        winners: "Closed items. Matches section, item name, winner name or winner paddle number.",
        bidders: "Matches paddle number, name, phone or address.",
        donors:  "Matches donor number, name, phone or address."
    };

    let timer = null;
    let latestRequest = 0;   // lets us ignore a slow, out-of-date reply

    function selectedType() {
        return form.querySelector('input[name="type"]:checked').value;
    }

    form.addEventListener("submit", function (e) {
        e.preventDefault();
        clearTimeout(timer);
        runSearch();
    });

    queryField.addEventListener("input", function () {
        clearTimeout(timer);
        timer = setTimeout(runSearch, 250);
    });

    typeRadios.forEach(function (radio) {
        radio.addEventListener("change", function () {
            clearTimeout(timer);
            runSearch();
        });
    });

    function runSearch() {
        const type = selectedType();
        const q = queryField.value.trim();
        const mine = ++latestRequest;

        hint.textContent = HINTS[type] + " Leave the box empty to list everything.";
        status.textContent = "Searching...";

        fetch("api/search.php?type=" + encodeURIComponent(type) + "&q=" + encodeURIComponent(q))
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                // A newer search has started since this one; its reply wins.
                if (mine !== latestRequest) {
                    return;
                }
                if (!result.success) {
                    table.hidden = true;
                    status.textContent = result.error;
                    return;
                }
                showResults(result);
            })
            .catch(function (err) {
                if (mine !== latestRequest) {
                    return;
                }
                table.hidden = true;
                status.textContent = "Search failed -- check your connection.";
                console.error(err);
            });
    }

    function showResults(result) {
        // Header row
        head.textContent = "";
        result.columns.concat(["Action"]).forEach(function (label) {
            const th = document.createElement("th");
            th.textContent = label;
            head.appendChild(th);
        });

        // Body rows. textContent (not innerHTML) so names can't inject HTML.
        body.textContent = "";
        result.rows.forEach(function (row) {
            const tr = document.createElement("tr");
            row.cells.forEach(function (text) {
                const td = document.createElement("td");
                td.textContent = text;
                tr.appendChild(td);
            });

            const actionCell = document.createElement("td");
            const link = document.createElement("a");
            link.href = row.link.href;
            link.textContent = row.link.label;
            actionCell.appendChild(link);
            tr.appendChild(actionCell);

            body.appendChild(tr);
        });

        const count = result.rows.length;
        table.hidden = (count === 0);
        if (count === 0) {
            status.textContent = "No matches found.";
        } else if (result.truncated) {
            status.textContent = "Showing the first " + count + " matches. Type more to narrow the search.";
        } else {
            status.textContent = count + (count === 1 ? " match." : " matches.");
        }
    }

    runSearch();   // show everything for the default type straight away
});
