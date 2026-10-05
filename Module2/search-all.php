<?php 
include("top.html");
require_once __DIR__ . '/../../../config.php';

$last_name  = $_GET['lastname']  ?? '';
$first_name = $_GET['firstname'] ?? '';

try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS
    );

    $last_name_safe        = $db->quote($last_name);
    $first_name_safe       = $db->quote($first_name);
    $first_name_paren_safe = $db->quote($first_name . ' (%');

$actor_stmt = $db->query(
    "SELECT id, first_name, last_name
     FROM actors
     WHERE last_name = $last_name_safe
       AND (first_name = $first_name_safe OR first_name LIKE $first_name_paren_safe)
     ORDER BY film_count DESC, id ASC
     LIMIT 1"
);

    $found_actor = null;

    foreach ($actor_stmt as $row) {
        $found_actor = $row;
    }

    if (!$found_actor) {
        echo "<p>Actor " . htmlspecialchars("$first_name $last_name") . " not found.</p>";
    } else {
        $actor_id_safe = $db->quote($found_actor['id']);

        // Query 1: all movies for this actor
        $movies_result = $db->query(
            "SELECT movies.name, movies.year
             FROM movies
             JOIN roles ON roles.movie_id = movies.id
             WHERE roles.actor_id = $actor_id_safe
             ORDER BY movies.year DESC, movies.name ASC"
        );

        echo "<table id='resultsTable'>";
        echo "<caption><topline>Results for " . htmlspecialchars($found_actor['first_name']) . " " . htmlspecialchars($found_actor['last_name']) . "</topline><br>"
    . "<bottomline>All films</bottomline></caption>";
        echo "<tr><th>#</th><th>Title</th><th>Year</th></tr>";

        $i = 1;
        foreach ($movies_result as $movie) {
            $row_class = ($i % 2 === 0) ? " class='alt'" : "";
            echo "<tr$row_class>";
            echo "<td>" . $i . "</td>";
            echo "<td>" . htmlspecialchars($movie['name']) . "</td>";
            echo "<td>" . htmlspecialchars($movie['year']) . "</td>";
            echo "</tr>";
            $i++;
        }

        echo "</table>";
    }

} catch (PDOException $e) {
    echo "Database error: " . htmlspecialchars($e->getMessage());
}

include("bottom.html");
?>