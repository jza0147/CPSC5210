// Checkout page.
//
// Flow: type the bidder's paddle number (their unpaid items load), choose how
// they are paying (and enter the check number for a check), check the total,
// then click Mark Paid. That records one payment, marks every one of their
// items paid, and closes their account.
//
// Unlike the other pages, this does NOT autosave: recording a payment is a
// one-way step, so it only happens when the cashier clicks Mark Paid and
// confirms. The server works out the real amount owed itself; the amount shown
// here is only sent back so the server can notice if it changed in the meantime.

document.addEventListener("DOMContentLoaded", function () {
    const status = document.getElementById("save-status");
    const form = document.getElementById("checkout-form");
    const paddleField = document.getElementById("paddle_number");
    const bidderInfo = document.getElementById("bidder-info");
    const itemsTable = document.getElementById("items-table");
    const itemsBody = document.getElementById("items-body");
    const checkWrap = document.getElementById("check-wrap");
    const checkField = document.getElementById("check_number");
    const subtotalEl = document.getElementById("subtotal");
    const feeRow = document.getElementById("fee-row");
    const feeEl = document.getElementById("fee");
    const totalEl = document.getElementById("total");
    const payBtn = document.getElementById("pay");
    const nextBtn = document.getElementById("next-bidder");
    const methodRadios = form.querySelectorAll('input[name="method"]');

    const METHOD_LABELS = { cash: "cash", check: "check", card: "credit card" };

    let data = null;          // what the server said this bidder owes, or null
    let loadedPaddle = null;  // paddle number currently on screen
    let paying = false;
    let finished = false;     // payment recorded; locked until Next Bidder

    form.addEventListener("submit", function (e) {
        e.preventDefault();
    });

    // Enter in the paddle field = done with this field.
    paddleField.addEventListener("keydown", function (e) {
        if (e.key === "Enter") {
            e.preventDefault();
            paddleField.blur();
        }
    });
    paddleField.addEventListener("blur", function () { loadCheckout("", false, false); });

    methodRadios.forEach(function (radio) {
        radio.addEventListener("change", function () {
            checkWrap.hidden = (selectedMethod() !== "check");
            updateTotals();
            updatePayButton();
            if (selectedMethod() === "check") {
                checkField.focus();
            }
        });
    });
    checkField.addEventListener("input", updatePayButton);

    payBtn.addEventListener("click", recordPayment);

    nextBtn.addEventListener("click", function () {
        if (paying) {
            return;
        }
        paddleField.value = "";
        clearAll();
        setStatus("Enter a paddle number to begin");
        paddleField.focus();
    });

    // ---- small helpers ----------------------------------------------------

    function setStatus(message) {
        status.textContent = message;
    }

    function money(cents) {
        return "$" + (cents / 100).toFixed(2);
    }

    function selectedMethod() {
        const checked = form.querySelector('input[name="method"]:checked');
        return checked ? checked.value : "";
    }

    function currentTotalCents() {
        if (!data) {
            return 0;
        }
        return data.subtotal_cents + (selectedMethod() === "card" ? data.card_fee_cents : 0);
    }

    function setControlsEnabled(enabled) {
        methodRadios.forEach(function (radio) { radio.disabled = !enabled; });
        checkField.disabled = !enabled;
    }

    function clearSelections() {
        methodRadios.forEach(function (radio) { radio.checked = false; });
        checkField.value = "";
        checkWrap.hidden = true;
    }

    function clearAll() {
        data = null;
        loadedPaddle = null;
        finished = false;
        bidderInfo.textContent = "";
        itemsBody.textContent = "";
        itemsTable.hidden = true;
        clearSelections();
        setControlsEnabled(true);
        updateTotals();
        updatePayButton();
    }

    function updateTotals() {
        const subtotal = data ? data.subtotal_cents : 0;
        const isCard = (selectedMethod() === "card");
        subtotalEl.textContent = money(subtotal);
        feeRow.hidden = !(data && isCard);
        feeEl.textContent = money(data && isCard ? data.card_fee_cents : 0);
        totalEl.textContent = money(currentTotalCents());
    }

    function updatePayButton() {
        const method = selectedMethod();
        const ready = data !== null &&
                      data.items.length > 0 &&
                      method !== "" &&
                      (method !== "check" || checkField.value.trim() !== "") &&
                      !paying &&
                      !finished;
        payBtn.disabled = !ready;
    }

    // ---- load what the bidder owes -----------------------------------------

    // force: reload even if this paddle is already on screen.
    // keepSelections: keep the chosen payment method / check number (used when
    // reloading the same bidder after the amount changed).
    function loadCheckout(note, force, keepSelections) {
        const raw = paddleField.value.trim();
        if (raw === "") {
            return;
        }
        if (!/^\d{1,3}$/.test(raw) || Number(raw) < 1) {
            setStatus("Paddle number must be a whole number from 1 to 999");
            return;
        }
        if (loadedPaddle === raw && !force) {
            return; // already showing this bidder
        }

        const keep = keepSelections ? { method: selectedMethod(), check: checkField.value } : null;
        clearAll();
        if (keep && keep.method) {
            const radio = form.querySelector('input[name="method"][value="' + keep.method + '"]');
            if (radio) {
                radio.checked = true;
                checkWrap.hidden = (keep.method !== "check");
                checkField.value = keep.check;
            }
        }
        setStatus("Looking up bidder...");

        fetch("api/get_checkout.php?paddle_number=" + encodeURIComponent(raw))
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (paddleField.value.trim() !== raw) {
                    return; // the paddle changed while we waited
                }
                if (!result.success) {
                    setStatus(result.error);
                    return;
                }
                data = result;
                loadedPaddle = raw;
                showCheckout(note);
            })
            .catch(function (err) {
                setStatus("Could not look up the bidder -- check your connection.");
                console.error(err);
            });
    }

    function showCheckout(note) {
        const prefix = note ? note + " " : "";
        bidderInfo.textContent = "Bidder: " + data.bidder.name;

        itemsBody.textContent = "";
        data.items.forEach(function (item) {
            const row = document.createElement("tr");
            [item.section, item.name, "$" + item.winning_bid].forEach(function (text) {
                const cell = document.createElement("td");
                cell.textContent = text;   // textContent, not innerHTML -- item names are user-entered
                row.appendChild(cell);
            });
            itemsBody.appendChild(row);
        });
        itemsTable.hidden = (data.items.length === 0);

        updateTotals();

        if (data.items.length === 0) {
            setControlsEnabled(false);
            setStatus(prefix + (data.paid_item_count > 0
                ? "All of this bidder's items have been paid for."
                : "This bidder has not won any items."));
        } else {
            setControlsEnabled(true);
            setStatus(prefix + data.items.length + " item" + (data.items.length === 1 ? "" : "s") +
                      " to pay. Choose a payment method.");
        }
        updatePayButton();
    }

    // ---- record the payment -------------------------------------------------

    function recordPayment() {
        if (data === null || paying || finished) {
            return;
        }
        const method = selectedMethod();
        const total = currentTotalCents();

        const question = "Record " + money(total) + " paid by " + METHOD_LABELS[method] +
                         " for paddle " + data.bidder.paddle_number + " (" + data.bidder.name + ")?";
        if (!window.confirm(question)) {
            return;
        }

        paying = true;
        updatePayButton();
        setStatus("Saving payment...");

        fetch("api/save_checkout.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                paddle_number: String(data.bidder.paddle_number),
                method: method,
                check_number: method === "check" ? checkField.value.trim() : "",
                expected_amount: (total / 100).toFixed(2)
            })
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (result.success) {
                    finished = true;
                    setControlsEnabled(false);
                    let message = "PAID: " + money(Math.round(Number(result.total) * 100)) + " by " +
                                  METHOD_LABELS[result.method];
                    if (result.method === "card") {
                        message += " (includes $" + result.card_fee + " card fee)";
                    }
                    message += " for " + result.item_count + " item" + (result.item_count === 1 ? "" : "s") +
                               ". Account closed for paddle " + result.paddle_number +
                               " (" + result.bidder_name + ").";
                    setStatus(message);
                    return;
                }

                setStatus(result.error);

                // The amount changed, or someone else already took payment:
                // refresh what is owed so the cashier sees the real picture.
                if (result.error_code === "AMOUNT_CHANGED" || result.error_code === "NOTHING_OWED") {
                    loadCheckout(result.error, true, true);
                }
            })
            .catch(function (err) {
                setStatus("Payment may not have been saved -- look the bidder up again to check before retrying.");
                console.error(err);
            })
            .finally(function () {
                paying = false;
                updatePayButton();
            });
    }
});
