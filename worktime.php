<?php

function getenvdefault($name, ?string $default = null): ?string
{
    if (($result = getenv($name)) === false) {
        return $default;
    }

    return $result;
}

function getHolidays($year)
{
    $easterDate = easter_date($year);

    $easterDay = date('j', $easterDate);
    $easterMonth = date('n', $easterDate);
    $easterYear = date('Y', $easterDate);

    // $bettag = strtotime('3 sunday', mktime(0, 0, 0, 9, 1, $year)); //betttag


    return [
        //   mktime(0, 0, 0, 12, 31, $year), // silvester
        mktime(0, 0, 0, 1, 1, $year), // neujahr
        mktime(0, 0, 0, 1, 2, $year), // berchtold
        mktime(0, 0, 0, 8, 1, $year), // 1. aug
        mktime(0, 0, 0, 5, 1, $year), // 1. mai
        mktime(0, 0, 0, 12, 25, $year), //weihnacht
        mktime(0, 0, 0, 12, 26, $year), //stephanstag
        // ostern
        mktime(0, 0, 0, $easterMonth, $easterDay - 2, $easterYear), //karfreitag
        mktime(0, 0, 0, $easterMonth, $easterDay + 1, $easterYear), //ostermontag
        mktime(0, 0, 0, $easterMonth, $easterDay + 39, $easterYear), //auffahrt
        mktime(0, 0, 0, $easterMonth, $easterDay + 51, $easterYear), //pfingst-montag
        //  strtotime('+1 day', $bettag), //bettmontag
    ];
}

function getHalfHolidays($year)
{
    $knabenschiessen = strtotime('2 sunday', mktime(0, 0, 0, 9, 1, $year));
    $knabenschiessen = strtotime('+1 day', $knabenschiessen);

    return [$knabenschiessen, getSechselauten($year)];
}

function getSechselauten($year)
{
    if (2030 === $year) {
        return mktime(0, 0, 0, 4, 29, $year);
    }

    //  see https://de.wikipedia.org/wiki/Sechsel%C3%A4uten#Datum
    $sechselauten = strtotime('3 monday', mktime(0, 0, 0, 4, 1, $year));
    $ostersonntag = easter_date($year);
    $secondsPerDay = 60 * 60 * 24;
    if ($ostersonntag + 1 * $secondsPerDay === $sechselauten) { // sechselauten am Ostermontag
        $sechselauten += 7 * $secondsPerDay;
    } elseif ($ostersonntag - 6 * $secondsPerDay === $sechselauten) { // sechselauten in Karwoche
        $sechselauten -= 7 * $secondsPerDay;
    }

    return $sechselauten;
}

function totalHours($since, $until)
{
    $token = getenv('TOGGL_TOKEN');
    $userIds = getenv('TOGGL_USER_IDS');
    $userAgent = getenv('TOGGL_USER_AGENT');
    if ($token === false) {
        die("set TOGGL_TOKEN\n");
    }
    if ($userIds === false) {
        die("set TOGGL_USER_IDS\n");
    }
    if ($userAgent === false) {
        die("set TOGGL_USER_AGENT\n");
    }
    $since = $since->format('Y-m-d');
    $until = $until->format('Y-m-d');
    $command = ' curl -s  -u '.$token.':api_token GET "https://toggl.com/reports/api/v2/summary?type=me&workspace_id=1006502&since='.$since.'&until='.$until.'&user_ids='.$userIds.'&user_agent='.$userAgent.'" ';

    $json = exec($command);
    $arr = json_decode($json, true);
    $totalH = $arr['total_grand'] / 1000 / 60 / 60;

    return $totalH;
}

function hoursToWork($since, $until)
{
    $startYear = (int) getenvdefault('TOGGL_START_YEAR', '2017');
    $startMonth = (int) getenvdefault('TOGGL_START_MONTH', 1);
    $daysOff = getDaysOff();
    $halfDaysOff = getDaysOff(false);

    $days = 0;
    while ($since <= $until) {
        $w = (int) $since->format('w');
        if ($w !== 0 && $w !== 6
            && !in_array($w, $daysOff, true)
            && (int) $since->format('U') > mktime(0, 0, 0, $startMonth, 1, $startYear)) {
            $hdays = getHolidays($since->format('Y'));
            if (!in_array($since->getTimestamp(), $hdays, true)) {
                $halfDays = getHalfHolidays($since->format('Y'));
                if (!in_array($w, $halfDaysOff, true) && in_array($since->getTimestamp(), $halfDays, true)) {
                    $days += 0.5;
                } elseif (in_array($w, $halfDaysOff, true)) {
                    $days += 0.5;
                }
                else {
                    $days++;
                }
            }
        }
        $since->modify('+1 day');
    }

    return $days * (40 / 5);
}

function getDaysOff($fullOffDays = true) {
    $daysOffMask = (int) getenvdefault('TOGGL_'.($fullOffDays ? '' : 'HALF_').'DAYS_OFF', 0);
    $daysOff = [];
    $day = 5;
    for ($dayMask = 16; $dayMask >= 1; $dayMask /= 2) {
        if($daysOffMask >= $dayMask) {
            $daysOff[] = $day;
            $daysOffMask -= $dayMask;
        }
        $day--;
    }

    return $daysOff;
}


function printHours($since, $until, $extraO = 0)
{
    $sinceDatetime = new DateTime($since);
    $untilDatetime = new DateTime($until);
    $format =  getenvdefault('TOGGL_SHOW_DATE_FORMAT', null);

    if ($format) {
        $since = $sinceDatetime->format($format);
        $until = $untilDatetime->format($format);
    }

    if ($since === $until) {
        echo $since.":\n";
    }
    else {
        echo $since.' - '.$until.":\n";
    }

    $t = totalHours($sinceDatetime, $untilDatetime);
    $w = hoursToWork($sinceDatetime, $untilDatetime);
    $o = $t - $w + $extraO;
    printf("%01.2f - %01.2f = %01.2f \n\n", $t, $w, $o);

    return $o;
}


printHours('today', 'today');
printHours('yesterday', 'yesterday');
printHours('last Sunday', 'last Sunday +6 days');
printHours('last Sunday -1 week', 'last Sunday -1 week +6 days');

$total = 0;
$thisYear = (int) date('Y');
$startYear = (int) getenvdefault('TOGGL_START_YEAR', '2017');

for ($year = $startYear; $year <= $thisYear; $year++) {
    $notHere = (int) getenv('TOGGL_AWAY_'.$year);
    $notHere *= 8;
    if ($year === $thisYear) {
        $total += printHours('01.01.'.$year, 'yesterday', $notHere);
    } else {
        $total += printHours('01.01.'.$year, '30.12.'.$year, $notHere);
    }
}

printf("%01.2f \n\n", $total);