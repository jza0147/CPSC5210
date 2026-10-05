<?php
include("top.html");
?>

<h1>Enter Winners</h1>
<p id="save-status" aria-live="polite">Enter a section to begin</p>

<form id="winner-form">
    <label for="section">Section</label><br>
    <input type="text" id="section" maxlength="50" autocomplete="off" autofocus><br>
    <p id="item-info"></p>

    <label for="paddle_number">Winning Paddle Number (1-999)</label><br>
    <input type="number" id="paddle_number" min="1" max="999" step="1"><br>
    <p id="bidder-info"></p>

    <label for="winning_bid">Winning Bid ($)</label><br>
    <input type="number" id="winning_bid" min="0.01" step="0.01"><br><br>

    <!-- Shown only for an item that is already closed and not yet paid. -->
    <button type="button" id="change-winner" hidden>Change Winner</button>
    <button type="button" id="next-item">Next Section</button>
</form>

<script src="js/winners.js"></script>

<?php include("bottom.html"); ?>
