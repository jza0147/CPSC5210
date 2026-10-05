// Autosave for the Enter Bidders page.
// Same pattern as Enter Items: fields save when the user leaves them.
// The first successful save inserts a row and returns bidder_id; every
// save after that includes the id, so the server updates instead.

document.addEventListener("DOMContentLoaded", function () {
    const status = document.getElementById("save-status");
    const form = document.getElementById("bidder-form");

    const fieldsToWatch = ["paddle_number", "name", "address", "phone"];

    // Pressing Enter in a field shouldn't submit/reload the page.
    form.addEventListener("submit", function (e) {
        e.preventDefault();
    });

    fieldsToWatch.forEach(function (fieldId) {
        document.getElementById(fieldId).addEventListener("blur", saveBidder);
    });

    // Bumped each time the form is cleared, so a save that was still in flight
    // for the previous bidder can't write its id into the new, blank form.
    let generation = 0;

    document.getElementById("new-bidder").addEventListener("click", function () {
        form.reset();
        generation++;
        savePending = false;
        hideDup();
        declinedName = null;
        document.getElementById("bidder_id").value = ""; // reset() doesn't clear hidden inputs
        status.textContent = "Not saved yet";
        // Drop ?bidder_id=... so refreshing the page doesn't bring the old bidder back.
        history.replaceState(null, "", window.location.pathname);
        document.getElementById("paddle_number").focus();
    });

    // If the user tabs through several fields quickly, a second save can start
    // before the first one has returned its bidder_id -- that would try to
    // insert the same bidder twice. So only one save runs at a time; anything
    // that happens meanwhile triggers one more save when the first finishes.
    let saving = false;
    let savePending = false;


    // ---- duplicate-name warning -------------------------------------------------------------
    // The server answers 409 DUPLICATE_NAME when another bidder already has this name. The clerk
    // can add it anyway (two people can share a name) or cancel.

    // The box normally comes from the page's HTML. If the page is an older copy without it,
    // build it here so the warning still works.
    if (!document.getElementById("dup-box")) {
        const box = document.createElement("div");
        box.id = "dup-box";
        box.className = "dup-box";
        box.setAttribute("role", "alert");
        box.hidden = true;
        box.style.cssText = "border:1px solid #e0b64a;background:#fff8e1;border-radius:6px;" +
                            "padding:0.6rem 1rem;margin:0 0 1rem;max-width:40rem";
        const msg = document.createElement("p");
        msg.id = "dup-msg";
        msg.style.fontWeight = "bold";
        const list = document.createElement("ul");
        list.id = "dup-list";
        const yes = document.createElement("button");
        yes.type = "button";
        yes.id = "dup-yes";
        yes.textContent = "Add anyway";
        const no = document.createElement("button");
        no.type = "button";
        no.id = "dup-no";
        no.textContent = "Cancel";
        box.append(msg, list, yes, " ", no);
        status.insertAdjacentElement("afterend", box);
    }

    const dupBox = document.getElementById("dup-box");
    const dupMsg = document.getElementById("dup-msg");
    const dupList = document.getElementById("dup-list");
    let dupOpen = false;          // a "add anyway?" question is on screen
    let declinedName = null;      // name the clerk said no to; no more saves until it changes

    function normName(value) {
        return value.replace(/\s+/g, " ").trim().toLowerCase();
    }

    function hideDup() {
        dupBox.hidden = true;
        dupOpen = false;
    }

    function showDup(result) {
        dupMsg.textContent = result.error + " Add another one anyway?";
        dupList.textContent = "";
        (result.matches || []).forEach(function (m) {
            const li = document.createElement("li");
            const details = [m.address, m.phone].filter(function (v) { return v; }).join(", ");
            li.textContent = "Bidder #" + m.id + ": " + m.name + (details ? " (" + details + ")" : "");
            dupList.appendChild(li);
        });
        dupBox.hidden = false;
        dupOpen = true;
        status.textContent = "Not saved yet -- waiting for your answer";
    }

    document.getElementById("dup-yes").addEventListener("click", function () {
        hideDup();
        doSave(true);
    });
    document.getElementById("dup-no").addEventListener("click", function () {
        hideDup();
        declinedName = normName(document.getElementById("name").value);
        status.textContent = "Not saved: that name already exists. Change the name to save.";
    });
    // Editing the name makes the old question (or the old "no") meaningless.
    document.getElementById("name").addEventListener("input", function () {
        if (normName(this.value) !== declinedName) {
            declinedName = null;
        }
        hideDup();
    });

    function saveBidder() {     // used by the blur handlers (they pass an event, so no arguments here)
        doSave(false);
    }

    function doSave(allowDuplicate) {
        if (dupOpen) {
            return;     // wait for the answer to the "add anyway?" question
        }
        if (saving) {
            savePending = true;
            return;
        }

        const data = {
            bidder_id: document.getElementById("bidder_id").value,
            paddle_number: document.getElementById("paddle_number").value.trim(),
            name: document.getElementById("name").value,
            address: document.getElementById("address").value,
            phone: document.getElementById("phone").value
        };

        // Need both a paddle number and a name before there is anything to save.
        if (data.paddle_number === "" || data.name.trim() === "") {
            status.textContent = "Enter a paddle number and a name to save";
            return;
        }

        // Friendly browser-side check; the server re-checks it for real.
        if (!/^\d{1,3}$/.test(data.paddle_number) ||
            Number(data.paddle_number) < 1) {
            status.textContent = "Paddle number must be a whole number from 1 to 999";
            return;
        }

        if (declinedName !== null && normName(data.name) === declinedName) {
            return;     // the clerk already said no to this name
        }
        if (allowDuplicate) {
            data.allow_duplicate = true;
        }

        const myGeneration = generation;
        saving = true;
        status.textContent = "Saving...";

        fetch("api/save_bidder.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        })
            .then(function (response) {
                // Read the reply as text first, so a PHP error page can be reported instead of
                // being mistaken for a lost connection.
                return response.text().then(function (text) {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        const plain = text.replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim();
                        return {
                            success: false,
                            error: "The server sent an unexpected reply (HTTP " + response.status + "): " +
                                   plain.slice(0, 200)
                        };
                    }
                });
            })
            .then(function (result) {
                if (myGeneration !== generation) {
                    return; // the form was cleared for a new bidder while this save ran
                }
                if (result.success) {
                    document.getElementById("bidder_id").value = result.bidder_id;
                    status.textContent = "Saved (Paddle " + result.paddle_number +
                                         ", Bidder #" + result.bidder_id + ")";
                } else if (result.error_code === "DUPLICATE_NAME") {
                    showDup(result);
                } else {
                    status.textContent = "Not saved: " + result.error;
                }
            })
            .catch(function (err) {
                status.textContent = "Save failed -- check your connection.";
                console.error(err);
            })
            .finally(function () {
                saving = false;
                if (savePending) {
                    savePending = false;
                    saveBidder();
                }
            });
    }

    // Opened from the Search page as bidders.php?bidder_id=9000: load that bidder
    // into the form. Saving then takes the update path, because bidder_id is filled in.
    function loadBidderFromUrl() {
        const id = new URLSearchParams(window.location.search).get("bidder_id");
        if (id === null) {
            return;
        }
        if (!/^\d+$/.test(id) || Number(id) < 1) {
            status.textContent = "That is not a valid bidder id";
            return;
        }

        const myGeneration = generation;
        status.textContent = "Loading bidder #" + id + "...";

        fetch("api/get_bidder_details.php?bidder_id=" + encodeURIComponent(id))
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (myGeneration !== generation) {
                    return; // Start New Bidder was clicked while this was loading
                }
                if (!result.success) {
                    status.textContent = result.error;
                    return;
                }

                const bidder = result.bidder;
                const blankIfNull = function (value) {
                    return (value === null || value === undefined) ? "" : value;
                };

                document.getElementById("bidder_id").value = bidder.bidder_id;
                document.getElementById("paddle_number").value = blankIfNull(bidder.paddle_number);
                document.getElementById("name").value = bidder.name;
                document.getElementById("address").value = blankIfNull(bidder.address);
                document.getElementById("phone").value = blankIfNull(bidder.phone);

                status.textContent = "Editing bidder #" + bidder.bidder_id + " - changes save automatically.";
            })
            .catch(function (err) {
                status.textContent = "Could not load the bidder -- check your connection.";
                console.error(err);
            });
    }

    loadBidderFromUrl();
});