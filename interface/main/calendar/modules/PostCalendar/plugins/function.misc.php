<?php

function get_bg_color($pc_eid)
{
    $row = sqlQuery("SELECT * FROM openemr_postcalendar_events_additional WHERE pc_eid = ?", array($pc_eid));
    return ($row['bg_color']) ? $row['bg_color'] : null;
}