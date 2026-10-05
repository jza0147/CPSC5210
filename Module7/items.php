<?php
include("top.html");
// Note: no db.php or donor query here anymore -- items.js loads the
// donor list itself via AJAX once the page is in the browser.
?>

<h1>Enter Items</h1>
<p id="save-status">Not saved yet</p>

<form id="item-form">
    <!-- Holds the item_id once the first save happens. Empty means
         "this is a brand new item, insert it." -->
    <input type="hidden" id="item_id" value="">

    <label for="name">Item Name</label><br>
    <input type="text" id="name" name="name"><br><br>

    <label for="description">Description</label><br>
    <textarea id="description" name="description"></textarea><br><br>

    <label for="section">Item #/Section</label><br>
    <input type="text" id="section" name="section" maxlength="50"><br>
    <br><br>

    <label for="price">Price</label><br>
    <input type="number" step="0.01" id="price" name="price"><br><br>

    <label for="suggested_start_price">Suggested Start Price</label><br>
    <input type="number" step="0.01" id="suggested_start_price" name="suggested_start_price"><br><br>

    <label for="donor_id">Donor</label><br>
    <select id="donor_id" name="donor_id">
        <option value="">-- Loading donors... --</option>
    </select><br><br>

    <!-- Clears the form so the next item is a new row, not an edit of this one. -->
    <button type="button" id="new-item">Start New Item</button>
</form>

<script src="js/items.js"></script>

<?php include("bottom.html"); ?>
