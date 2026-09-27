<?php
// Festival date status shared by admin (festivals.php, dashboard.php) and the package pages.
//
// A festival is "provisional" until its dates come from the government's official holiday list
// (Verification 'official'); seasonal guidance rows are never official. BS 2084 starts on
// 14 Apr 2027 (Nepali New Year); its official list is usually published a few months before,
// so from FESTIVAL_BS2084_REVIEW_FROM the dashboard reminds the admin to re-check those dates.

const FESTIVAL_BS2084_START = '2027-04-14';
const FESTIVAL_BS2084_REVIEW_FROM = '2027-02-01';

function festival_is_provisional(object $festival): bool
{
    return !in_array((string)$festival->Verification, ['official', 'guidance'], true);
}

// ['count' => provisional BS 2084 festivals, 'due' => true once the review date has come].
function festival_bs2084_status(PDO $dbh): array
{
    $count = (int)$dbh->query("SELECT COUNT(*) FROM tblfestivals WHERE StartDate >= '" . FESTIVAL_BS2084_START . "'
        AND Verification NOT IN ('official', 'guidance')")->fetchColumn();
    return ['count' => $count, 'due' => $count > 0 && date('Y-m-d') >= FESTIVAL_BS2084_REVIEW_FROM];
}
