// Autosave for the Enter Items page.
// Every field saves itself when the user leaves it (on "blur" for text
// fields, on "change" for the dropdown). The first save has no item_id
// yet, so the server inserts a new row and hands the id back to us;
// every save after that includes the id, so the server updates instead.

document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("item-form");
    const status = document.getElementById("save-status");

    // Load the donor list first. Then, if we were opened from the Search page
    // (items.php?item_id=1000), load that item into the form for editing -- after
    // the dropdown has its options, so the item's donor can be selected.
    loadDonors().then(loadItemFromUrl);

    const fieldsToWatch = ["name", "description", "section",
                            "price", "suggested_start_price", "donor_id"];

    fieldsToWatch.forEach(function (fieldId) {
        const field = document.getElementById(fieldId);
        const eventName = (field.tagName === "SELECT") ? "change" : "blur";
        field.addEventListener(eventName, saveItem);
    });

    // Bumped each time the form is cleared, so a save that was still in flight
    // for the previous item can't write its id into the new, blank form.
    let generation = 0;

    document.getElementById("new-item").addEventListener("click", function () {
        form.reset();
        generation++;
        savePending = false;
        document.getElementById("item_id").value = ""; // reset() doesn't clear hidden inputs
        status.textContent = "Not saved yet";
        // Drop ?item_id=... so refreshing the page doesn't bring the old item back.
        history.replaceState(null, "", window.location.pathname);
        document.getElementById("name").focus();
    });

    function loadDonors() {
        const select = document.getElementById("donor_id");

        return fetch("api/get_donors.php")
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (!result.success) {
                    select.innerHTML = "";
                    const opt = document.createElement("option");
                    opt.value = "";
                    opt.textContent = "-- Could not load donors --";
                    select.appendChild(opt);
                    return;
                }

                // Clear the "Loading donors..." placeholder.
                select.innerHTML = "";

                const placeholder = document.createElement("option");
                placeholder.value = "";
                placeholder.textContent = "-- Select a donor --";
                select.appendChild(placeholder);

                result.donors.forEach(function (donor) {
                    const opt = document.createElement("option");
                    opt.value = donor.donor_id;
                    opt.textContent = donor.name; // textContent, not innerHTML -- safe from XSS
                    select.appendChild(opt);
                });
            })
            .catch(function (err) {
                console.error(err);
            });
    }

    // Opened from the Search page as items.php?item_id=1000: load that item into
    // the form. Saving then takes the update path, because item_id is filled in.
    function loadItemFromUrl() {
        const id = new URLSearchParams(window.location.search).get("item_id");
        if (id === null) {
            return;
        }
        if (!/^\d+$/.test(id) || Number(id) < 1) {
            status.textContent = "That is not a valid item";
            return;
        }

        const myGeneration = generation;
        status.textContent = "Loading item...";

        fetch("api/get_item.php?item_id=" + encodeURIComponent(id))
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (myGeneration !== generation) {
                    return; // Start New Item was clicked while this was loading
                }
                if (!result.success) {
                    status.textContent = result.error;
                    return;
                }

                const item = result.item;
                const blankIfNull = function (value) {
                    return (value === null || value === undefined) ? "" : value;
                };

                document.getElementById("item_id").value = item.item_id;
                document.getElementById("name").value = item.name;
                document.getElementById("description").value = blankIfNull(item.description);
                document.getElementById("section").value = blankIfNull(item.section);
                document.getElementById("price").value = blankIfNull(item.price);
                document.getElementById("suggested_start_price").value = blankIfNull(item.suggested_start_price);
                document.getElementById("donor_id").value = blankIfNull(item.donor_id);

                status.textContent = "Editing \"" + item.name + "\" - changes save automatically.";
            })
            .catch(function (err) {
                status.textContent = "Could not load the item -- check your connection.";
                console.error(err);
            });
    }

    // Only one save at a time. Without this, tabbing quickly through the
    // fields can fire two saves before the first returns an item_id, and
    // the server would insert the same item twice.
    let saving = false;
    let savePending = false;

    function saveItem() {
        if (saving) {
            savePending = true;
            return;
        }

        const data = {
            item_id: document.getElementById("item_id").value,
            name: document.getElementById("name").value,
            description: document.getElementById("description").value,
            section: document.getElementById("section").value,
            price: document.getElementById("price").value,
            suggested_start_price: document.getElementById("suggested_start_price").value,
            donor_id: document.getElementById("donor_id").value
        };

        // Don't bother saving an item with no name yet.
        if (data.name.trim() === "") {
            return;
        }

        const myGeneration = generation;
        saving = true;
        status.textContent = "Saving...";

        fetch("api/save_item.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (myGeneration !== generation) {
                    return; // the form was cleared for a new item while this save ran
                }
                if (result.success) {
                    // Remember the id so future saves are updates, not inserts.
                    document.getElementById("item_id").value = result.item_id;
                    status.textContent = data.section.trim() === ""
                        ? "Saved -- but it has no section yet, so it can't be found when entering winners."
                        : "Saved (Section " + data.section.trim() + ")";
                } else {
                    status.textContent = "Error: " + result.error;
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
                    saveItem();
                }
            });
    }
});
