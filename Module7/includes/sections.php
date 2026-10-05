<?php
// Sections are how items are labeled, sorted and found at the auction, so every list that
// shows items puts them in section order. The item number is only the database's own key.
//
// Order: sections that start with a number come first, in number order (2, 10, 10B, 11 --
// not 10, 11, 2); then sections that start with a letter, A to Z; items with no section last.
// $alias is the table alias the query uses for items.
function sectionOrder($alias = 'i')
{
    return "$alias.section IS NULL, "
         . "($alias.section NOT REGEXP '^[0-9]'), "
         . "CAST($alias.section AS UNSIGNED), "
         . "$alias.section, $alias.item_id";
}

// What to show where a section would go.
function sectionText($section)
{
    return ($section === null || trim((string) $section) === '') ? '(none)' : (string) $section;
}
