<?php
include("top.html");
?>

<!-- Layout and print rules for this page (bid sheets, hiding the controls when printing). -->
<link href="reports.css" type="text/css" rel="stylesheet" />

<h1>Reports</h1>

<form id="report-controls">
    <label for="report">Report</label>
    <select id="report">
        <option value="">-- Choose a report --</option>
        <option value="items">Item list</option>
        <option value="bid-sheets">Item bid sheets (printable)</option>
        <option value="not-bid-on">Items not bid on</option>
        <option value="winners-running">Running list of winners</option>
        <option value="unpaid">Items not paid for</option>
        <option value="won-by-bidder">Items won by bidder</option>
        <option value="by-donor">Items donated by donor</option>
        <option value="donors">Donor list</option>
        <option value="bidders">Bidder list</option>
    </select>

    <span id="param-paddle" hidden>
        <label for="p_paddle">Paddle number (1-999)</label>
        <input type="number" id="p_paddle" min="1" max="999" step="1">
    </span>

    <span id="param-donor" hidden>
        <label for="p_donor">Donor</label>
        <select id="p_donor"><option value="">-- Loading donors... --</option></select>
    </span>

    <span id="param-scroll" hidden>
        <label><input type="checkbox" id="autoscroll"> Auto-scroll</label>
    </span>

    <span id="param-feed" hidden>
        <a href="winners-feed.html" target="_blank" rel="noopener">Open projector display</a>
    </span>

    <button type="button" id="print-btn" disabled>Print</button>
</form>

<p id="report-status" aria-live="polite">Choose a report.</p>

<div id="report-output"></div>

<script src="js/reports.js"></script>

<?php include("bottom.html"); ?>
