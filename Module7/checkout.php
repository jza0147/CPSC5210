<?php
require "includes/money.php";   // for the card fee percentage shown below
include("top.html");
?>

<h1>Checkout</h1>
<p id="save-status" aria-live="polite">Enter a paddle number to begin</p>

<form id="checkout-form">
    <label for="paddle_number">Paddle Number (1-999)</label><br>
    <input type="number" id="paddle_number" min="1" max="999" step="1" autofocus><br>
    <p id="bidder-info"></p>

    <table id="items-table" hidden>
        <thead>
            <tr><th>Section</th><th>Item</th><th>Winning Bid</th></tr>
        </thead>
        <tbody id="items-body"></tbody>
    </table>

    <fieldset id="method-box">
        <legend>Payment Method</legend>
        <label><input type="radio" name="method" value="cash"> Cash</label>
        <label><input type="radio" name="method" value="check"> Check</label>
        <label><input type="radio" name="method" value="card"> Credit Card
            (<?= CARD_FEE_BASIS_POINTS / 100 ?>% fee)</label>
    </fieldset>

    <div id="check-wrap" hidden>
        <label for="check_number">Check Number</label><br>
        <input type="text" id="check_number" maxlength="30"><br>
    </div>

    <p>Subtotal: <span id="subtotal">$0.00</span></p>
    <p id="fee-row" hidden>Credit card fee: <span id="fee">$0.00</span></p>
    <p><strong>Total due: <span id="total">$0.00</span></strong></p>

    <button type="button" id="pay" disabled>Mark Paid</button>
    <button type="button" id="next-bidder">Next Bidder</button>
</form>

<script src="js/checkout.js"></script>

<?php include("bottom.html"); ?>
