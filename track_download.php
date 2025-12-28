<?php
// track_download.php - Track file downloads
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine if it's a song or report download
    $type = isset($_GET['type']) ? $_GET['type'] : 'song';
    
    if ($type === 'report' && isset($_POST['report_id'])) {
        $report_id = intval($_POST['report_id']);
        $sql = "UPDATE reports SET download_count = download_count + 1 WHERE id = '$report_id'";
        mysqli_query($conn, $sql);
    } elseif (isset($_POST['song_id'])) {
        $song_id = intval($_POST['song_id']);
        $sql = "UPDATE songs SET download_count = download_count + 1 WHERE id = '$song_id'";
        mysqli_query($conn, $sql);
    }
}