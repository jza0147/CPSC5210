// Enter Winners page.
//
// Flow: type the section (the item loads), type the winning paddle number
// (shows who that is), type the winning bid. As soon as all three are valid
// the page saves, and the server closes the item.
//
// Items are found by their section, the label on the item and its bid sheet. The item's
// own database number is used behind the scenes only, and never shown.
//
// Saving the first time "claims" the item. After that this page is allowed to
// correct it (overwrite = true). If another clerk closed the item first, the
// server refuses and we show who won it instead of silently overwriting.

document.addEventListener("DOMContentLoaded", function () {
    const status = document.getElementById("save-status");
    const form = document.getElementById("winner-form");
    const itemField = document.getElementById("section");
    const paddleField = document.getElementById("paddle_number");
    const bidField = document.getElementById("winning_bid");
    const itemInfo = document.getElementById("item-info");
    const bidderInfo = document.getElementById("bidder-info");
    const changeBtn = document.getElementById("change-winner");
    const nextBtn = document.getElementById("next-item");

    let loadedItemId = null;  // the item on screen: its database key (a string), or null
    let loadedSection = null; // the section the clerk typed to find it
    let claimed = false;      // true once this page closed the item / Change Winner was clicked
    let saving = false;
    let savePending = false;

    form.addEventListener("submit", function (e) {
        e.preventDefault();
    });

    // Enter in a field = "I'm done with this field" (same as tabbing away).
    [itemField, paddleField, bidField].forEach(function (field) {
        field.addEventListener("keydown", function (e) {
            if (e.key === "Enter") {
                e.preventDefault();
                field.blur();
            }
        });
    });

    itemField.addEventListener("blur", function () { loadItem(false, ""); });
    paddleField.addEventListener("blur", function () { lookupBidder(); trySave(); });
    bidField.addEventListener("blur", trySave);

    changeBtn.addEventListener("click", function () {
        claimed = true;
        lockFields(false);
        changeBtn.hidden = true;
        setStatus("Editing the winner - changes save automatically.");
        paddleField.focus();
    });

    nextBtn.addEventListener("click", function () {
        if (saving) {
            setStatus("Still saving - try again in a moment.");
            return;
        }
        loadedItemId = null;
        loadedSection = null;
        claimed = false;
        savePending = false;
        itemField.value = "";
        clearEntryFields();
        itemInfo.textContent = "";
        setStatus("Enter a section to begin");
        // Drop ?section=... so refreshing the page doesn't bring the old item back.
        history.replaceState(null, "", window.location.pathname);
        itemField.focus();
    });

    function setStatus(message) {
        status.textContent = message;
    }

    function lockFields(locked) {
        paddleField.disabled = locked;
        bidField.disabled = locked;
    }

    function clearEntryFields() {
        paddleField.value = "";
        bidField.value = "";
        bidderInfo.textContent = "";
        lockFields(false);
        changeBtn.hidden = true;
    }

    function money(value) {
        return "$" + Number(value).toFixed(2);
    }

    // ---- Look up the item ------------------------------------------------

    function loadItem(force, note) {
        const raw = itemField.value.trim();
        if (raw === "") {
            return;
        }
        if (loadedSection === raw && !force) {
            return; // already showing this item
        }

        loadedItemId = null;
        loadedSection = null;
        claimed = false;
        savePending = false;
        clearEntryFields();
        itemInfo.textContent = "Looking up item...";

        fetch("api/get_item.php?section=" + encodeURIComponent(raw))
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                // The clerk may have typed a different section while we waited.
                if (itemField.value.trim() !== raw) {
                    return;
                }
                if (!result.success) {
                    itemInfo.textContent = "";
                    setStatus(result.error);
                    return;
                }
                showItem(result.item, raw, note);
            })
            .catch(function (err) {
                itemInfo.textContent = "";
                setStatus("Could not look up that section -- check your connection.");
                console.error(err);
            });
    }

    function itemTitle(item) {
        return "Section " + (item.section || "(none)");
    }

    function showItem(item, typedSection, note) {
        loadedItemId = String(item.item_id);
        loadedSection = typedSection;
        const prefix = note ? note + " " : "";

        if (item.status === "closed") {
            itemInfo.textContent = itemTitle(item) + " - CLOSED";
            paddleField.value = item.paddle_number;
            bidField.value = item.winning_bid;
            bidderInfo.textContent = "Bidder: " + item.winner_name;
            lockFields(true);

            if (item.paid) {
                setStatus(prefix + "This item is closed and PAID, so its winner can't be changed.");
            } else {
                changeBtn.hidden = false;
                setStatus(prefix + "Winner: paddle " + item.paddle_number + " (" + item.winner_name +
                          ") for " + money(item.winning_bid) + ". Click Change Winner to correct it.");
            }
        } else {
            itemInfo.textContent = itemTitle(item) + " - open";
            lockFields(false);
            setStatus(prefix + "Enter the winning paddle number and bid.");
            paddleField.focus();
        }
    }

    // ---- Look up the bidder ----------------------------------------------

    function lookupBidder() {
        const raw = paddleField.value.trim();
        bidderInfo.textContent = "";
        if (raw === "" || paddleField.disabled) {
            return;
        }
        if (!/^\d{1,3}$/.test(raw) || Number(raw) < 1) {
            bidderInfo.textContent = "Paddle number must be a whole number from 1 to 999";
            return;
        }

        fetch("api/get_bidder.php?paddle_number=" + encodeURIComponent(raw))
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (paddleField.value.trim() !== raw) {
                    return; // paddle changed while we waited
                }
                bidderInfo.textContent = result.success
                    ? "Bidder: " + result.bidder.name
                    : result.error;
            })
            .catch(function (err) {
                console.error(err);
            });
    }

    // ---- Save the winner -------------------------------------------------

    function trySave() {
        if (loadedItemId === null || paddleField.disabled) {
            return;
        }
        if (saving) {
            savePending = true;
            return;
        }

        const paddle = paddleField.value.trim();
        const bid = bidField.value.trim();

        // Wait until both are filled in; the status line already says what's needed.
        if (paddle === "" || bid === "") {
            return;
        }
        if (!/^\d{1,3}$/.test(paddle) || Number(paddle) < 1) {
            setStatus("Paddle number must be a whole number from 1 to 999");
            return;
        }
        if (!/^\d{1,8}(\.\d{1,2})?$/.test(bid) || Number(bid) <= 0) {
            setStatus("Winning bid must be greater than 0, with at most 2 decimal places");
            return;
        }

        const itemId = loadedItemId;
        const sectionAtSend = loadedSection;
        saving = true;
        setStatus("Saving...");

        fetch("api/save_winner.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                item_id: itemId,
                paddle_number: paddle,
                winning_bid: bid,
                overwrite: claimed
            })
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                const stillOnThisItem = (loadedItemId === itemId);

                if (result.success) {
                    const summary = "Saved: section " + (result.section || "") + " closed - paddle " +
                                    result.paddle_number + " (" + result.bidder_name + ") for " +
                                    money(result.winning_bid);
                    setStatus(summary);
                    if (stillOnThisItem) {
                        claimed = true;
                        changeBtn.hidden = true;
                        itemInfo.textContent = itemInfo.textContent.replace(/ - (open|CLOSED)$/, "") + " - CLOSED";
                        bidderInfo.textContent = "Bidder: " + result.bidder_name;
                    }
                    return;
                }

                // Not saved. If the clerk already moved on, say which item it was about.
                const message = stillOnThisItem ? result.error : "Section " + sectionAtSend + ": " + result.error;
                setStatus(message);

                // Someone else got there first (or it has been paid): show the real
                // current winner instead of leaving stale values on screen.
                if (stillOnThisItem &&
                    (result.error_code === "ALREADY_CLOSED" || result.error_code === "ALREADY_PAID")) {
                    loadItem(true, result.error + ".");
                }
            })
            .catch(function (err) {
                setStatus("Save failed -- check your connection.");
                console.error(err);
            })
            .finally(function () {
                saving = false;
                if (savePending) {
                    savePending = false;
                    trySave();
                }
            });
    }

    // Opened from the Search page as winners.php?section=12: load that item straight away
    // (a closed one shows its winner and the Change Winner button).
    const startSection = new URLSearchParams(window.location.search).get("section");
    const startKey = new URLSearchParams(window.location.search).get("item_id");   // items that have no section
    if (startSection !== null && startSection.trim() !== "") {
        itemField.value = startSection.trim();
        loadItem(false, "");
    } else if (startKey !== null && /^\d+$/.test(startKey) && Number(startKey) >= 1) {
        // Rare: an item with no section can't be typed in, so open it by its key.
        fetch("api/get_item.php?item_id=" + encodeURIComponent(startKey))
            .then(function (response) { return response.json(); })
            .then(function (result) {
                if (result.success) {
                    itemField.value = result.item.section || "";
                    showItem(result.item, itemField.value, "");
                } else {
                    setStatus(result.error);
                }
            })
            .catch(function (err) { console.error(err); });
    }
});
