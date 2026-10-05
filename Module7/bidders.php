<?php
include("top.html");
?>

<h1>Enter Bidders</h1>
<p id="save-status">Not saved yet</p>

<div id="dup-box" class="dup-box" role="alert" hidden>
    <p id="dup-msg"></p>
    <ul id="dup-list"></ul>
    <button type="button" id="dup-yes">Add anyway</button>
    <button type="button" id="dup-no">Cancel</button>
</div>

<form id="bidder-form">
    <!-- Empty = a brand new bidder (insert). Filled in after the first save (update). -->
    <input type="hidden" id="bidder_id" value="">

    <label for="paddle_number">Paddle Number (1-999)</label><br>
    <input type="number" id="paddle_number" name="paddle_number"
           min="1" max="999" step="1" required><br><br>

    <label for="name">Name</label><br>
    <input type="text" id="name" name="name" maxlength="100" required><br><br>

    <label for="address">Address</label><br>
    <input type="text" id="address" name="address" maxlength="200"><br><br>

    <label for="phone">Phone Number</label><br>
    <input type="text" id="phone" name="phone" maxlength="20"><br><br>

    <!-- Clears the form so the next bidder is a new row, not an edit of this one. -->
    <button type="button" id="new-bidder">Start New Bidder</button>
</form>

<script src="js/bidders.js"></script>

<?php include("bottom.html"); ?>
