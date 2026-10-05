<?php
include("top.html");
?>

<h1>Search</h1>

<form id="search-form">
    <fieldset>
        <legend>Search for</legend>
        <label><input type="radio" name="type" value="items" checked> Items</label>
        <label><input type="radio" name="type" value="winners"> Winners</label>
        <label><input type="radio" name="type" value="bidders"> Bidders</label>
        <label><input type="radio" name="type" value="donors"> Donors</label>
    </fieldset>
    <br>

    <label for="q">Search text</label><br>
    <input type="text" id="q" maxlength="100" autocomplete="off" autofocus>
    <button type="submit">Search</button>
</form>

<p id="search-hint"></p>
<p id="search-status" aria-live="polite"></p>

<table id="results" hidden>
    <thead><tr id="results-head"></tr></thead>
    <tbody id="results-body"></tbody>
</table>

<script src="js/search.js"></script>

<?php include("bottom.html"); ?>
