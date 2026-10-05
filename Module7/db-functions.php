<?php
include("top.html");
?>

<h1>Database Functions</h1>
<p id="db-status" aria-live="polite"></p>

<h2>Save database</h2>
<p>Saves a copy of everything (donors, bidders, items, payments) to the <code>backups</code> folder, named with today's date and time.</p>
<button type="button" id="save-btn">Save</button>

<h2>Save database as</h2>
<form id="saveas-form">
    <label for="saveas-name">Backup name</label>
    <input type="text" id="saveas-name" maxlength="60" placeholder="before-checkout">
    <button type="submit" id="saveas-btn">Save As</button>
</form>
<p id="replace-box" hidden>
    <span id="replace-msg"></span>
    <button type="button" id="replace-yes">Replace it</button>
    <button type="button" id="replace-no">Cancel</button>
</p>

<h2>Saved backups</h2>
<p id="backup-note"></p>
<table id="backup-table" hidden>
    <thead><tr><th>Name</th><th>Saved</th><th>Size</th><th></th><th></th></tr></thead>
    <tbody id="backup-body"></tbody>
</table>

<div id="restore-box" hidden>
    <h3>Restore <span id="restore-name"></span></h3>
    <p>This replaces <strong>everything</strong> currently in the database with the contents of that backup.
       A safety copy of the current data is saved first.</p>
    <label for="restore-confirm">Type RESTORE to confirm</label>
    <input type="text" id="restore-confirm" autocomplete="off">
    <button type="button" id="restore-go" disabled>Restore</button>
    <button type="button" id="restore-cancel">Cancel</button>
</div>

<h2>Clear database</h2>
<p>Deletes <strong>all</strong> donors, bidders, items and payments and starts the numbering over
   (items at 1000, donors at 5000, bidders at 9000). A safety copy is saved first, so it can be undone with Restore.</p>
<p id="clear-nobackup-row" hidden>
    <label><input type="checkbox" id="clear-nobackup">
    Clear anyway, <strong>without a safety copy</strong> (cannot be undone)</label>
</p>
<label for="clear-confirm">Type CLEAR to confirm</label>
<input type="text" id="clear-confirm" autocomplete="off">
<button type="button" id="clear-btn" disabled>Clear Database</button>

<script src="js/db-functions.js"></script>

<?php include("bottom.html"); ?>
