<?php

if (!file_exists(__DIR__.'/settings.php')) {
    die("\nPlease create and configure settings.php\n\n");
}
require_once __DIR__.'/settings.php';

function getHolidays($year)
{
    $easterDate = easter_date($year);

    $easterDay = date('j', $easterDate);
    $easterMonth = date('n', $easterDate);
    $easterYear = date('Y', $easterDate);

    // $bettag = strtotime('3 sunday', mktime(0, 0, 0, 9, 1, $year)); //betttag

    return [
        mktime(0, 0, 0, 12, 31, $year), // silvester
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
        mktime(0, 0, 0, $easterMonth, $easterDay + 50, $easterYear), //pfingst-montag
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

function totalHours($since, $until, $config)
{
    if (!array_key_exists('TOGGL_API_TOKEN', $config)) {
        die("set TOGGL_API_TOKEN\n");
    }
    if (!array_key_exists('TOGGL_USER_IDS', $config)) {
        die("set TOGGL_USER_IDS\n");
    }
    if (!array_key_exists('TOGGL_USER_AGENT', $config)) {
        die("set TOGGL_USER_AGENT\n");
    }
    $since = $since->format('Y-m-d');
    $until = $until->format('Y-m-d');
    $command = ' curl -s  -u '.$config['TOGGL_API_TOKEN'].':api_token GET "https://toggl.com/reports/api/v2/summary?type=me&workspace_id=1006502&since='.$since.'&until='.$until.'&user_ids='.$config['TOGGL_USER_IDS'].'&user_agent='.$config['TOGGL_USER_AGENT'].'" ';

    $json = exec($command);
    $arr = json_decode($json, true);

    return $arr['total_grand'] / 1000 / 60 / 60;
}

function hoursToWork($since, $until, $config)
{
    $daysOff = array_key_exists('DAYS_OFF', $config) ? $config['DAYS_OFF'] : [];
    $halfDaysOff = array_key_exists('HALF_DAYS_OFF', $config) ? $config['HALF_DAYS_OFF'] : [];

    $days = 0;
    while ($since <= $until) {
        $vacationDays = getVacationDays($config, (int) $until->format('Y'));
        $otherLeaveDays = getOtherLeaveDays($config, (int)  $until->format('Y'));
        if (!in_array($since, $vacationDays, false) && !in_array($since, $otherLeaveDays, false)) {
            // no hours to work in vacation or other leave
            $w = (int) $since->format('w');

            if ($w !== 0 && $w !== 6
                && !in_array($w, $daysOff, true)
                && (int) $since->getTimestamp() >= mktime(0, 0, 0, $config['START_MONTH'], 1, $config['START_YEAR'])) {
                // current day is not a weekend day and bigger then the start day
                $hdays = getHolidays($since->format('Y'));

                if (!in_array($since->getTimestamp(), $hdays, true)) {
                    // current day is not  a public holiday
                    $halfDays = getHalfHolidays($since->format('Y'));

                    if (!in_array($w, $halfDaysOff, true) && in_array($since->getTimestamp(), $halfDays, true)) {
                        // current day is not a weekly half day off but a public half holiday --> add half a day to work
                        $days += 0.5;

                    } elseif (in_array($w, $halfDaysOff, true)) {
                        // current day is a weekly half day off (but not a public holiday) --> add half a day to work
                        $days += 0.5;

                    } else {
                        // current day is neither a public holiday nor a weekly day off --> add a full day to work
                        $days++;
                    }
                }
            }
        }
        $since->modify('+1 day');
    }

    return $days * 8; // 8 hours to work each day
}

function printHours($since, $until, $config, $extraO = 0)
{
    $sinceDatetime = new DateTime($since);
    $untilDatetime = new DateTime($until);
    if (array_key_exists('DISPLAY_DATE_FORMAT', $config) && $config['DISPLAY_DATE_FORMAT'] !== '') {
        $since = $sinceDatetime->format($config['DISPLAY_DATE_FORMAT']);
        $until = $untilDatetime->format($config['DISPLAY_DATE_FORMAT']);
    }

    echo "\n";
    if ($since === $until) {
        echo $since.":\n";
    } else {
        echo $since.' - '.$until.":\n";
    }

    $t = totalHours($sinceDatetime, $untilDatetime, $config);
    $w = hoursToWork($sinceDatetime, $untilDatetime, $config);
    $o = $t - $w + $extraO;
    printf("  %01.2f - %01.2f = %01.2f \n", $t, $w, $o);

    return $o;
}

function printVacationInfo($config, $year)
{
    printf("  Vacation taken: %01.1fd \n", count(getVacationDays($config, $year, new DateTime('today'))));
    printf("  Vacation planed: %01.1fd \n", count(getVacationDays($config, $year, null)));
    printf("  Vacation unplanned: %01.1fd \n", getAmountNotPlanedVacationDays($config, $year));
    printf("  Other leave taken: %01.1fd \n", count(getOtherLeaveDays($config, $year, new DateTime('today'))));
}

function getVacationDays($config, $year, $until = null)
{
    if (!array_key_exists('VACATION', $config) || !array_key_exists($year, $config['VACATION'])) {
        return [];
    }
    $vacationDays = [];
    foreach ($config['VACATION'][$year] as $vacation) {
        if (!array_key_exists('FROM', $vacation) || !array_key_exists('UNTIL', $vacation)) {
            die('current vacation not configured correctly with \'FROM\' and \'UNTIL\'');
        }
        if ($vacation['FROM'] === null || $vacation['UNTIL'] === null) {
            break;
        }
        $from = clone $vacation['FROM'];
        while ($from <= $vacation['UNTIL']) {
            if ($until === null || $from <= $until) {
                if(hoursToWorkAtDay($config, $from) > 0) {
                    $vacationDays[] = clone $from;
                }
            }
            $from->modify('+1 day');
        }
    }

    return $vacationDays;
}

function getOtherLeaveDays($config, $year, $until = null)
{
    if (!array_key_exists('OTHER-LEAVE', $config) || !array_key_exists($year, $config['OTHER-LEAVE'])) {
        return [];
    }
    $otherLeaveDays = [];
    foreach ($config['OTHER-LEAVE'][$year] as $otherLeave) {
        if (!array_key_exists('FROM', $otherLeave) || !array_key_exists('UNTIL', $otherLeave)) {
            die('current "OTHER-LEAVE" not configured correctly with \'FROM\' and \'UNTIL\'');
        }
        if ($otherLeave['FROM'] === null || $otherLeave['UNTIL'] === null) {
            break;
        }
        $from = clone $otherLeave['FROM'];
        while ($from <= $otherLeave['UNTIL']) {
            if ($until === null || $from <= $until) {
                if(hoursToWorkAtDay($config, $from) > 0) {
                    $otherLeaveDays[] = clone $from;
                }
            }
            $from->modify('+1 day');
        }
    }

    return $otherLeaveDays;
}

function hoursToWorkAtDay($config, $day)
{
    $w = (int) $day->format('w');
    if ($w === 0 || $w === 6) {
        // no hours on weekend
        return 0;
    }

    if (array_key_exists('DAYS_OFF', $config) && in_array($w, $config['DAYS_OFF'], false)) {
        // no hours on days off
        return 0;
    }

    if (in_array($day->getTimestamp(), getHolidays((int) $day->format('Y')), true)) {
        // no hours on public holidays
        return 0;
    }

    if (array_key_exists('HALF_DAYS_OFF', $config) && in_array($w, $config['HALF_DAYS_OFF'], false)) {
        if (in_array($day->getTimestamp(), getHalfHolidays((int) $day->format('Y')), true)) {
            // no hours to work on a half day off on a half public holiday
            return 0;
        } else {
            // 4 hours to work on a half day off
            return 4;
        }
    }

    if (in_array($day->getTimestamp(), getHalfHolidays((int) $day->format('Y')), true)) {
        // 4 hours to work on a half public holiday
        return 4;
    }

    // 8 hours to work on a "normal" day
    return 8;

}

function getAmountNotPlanedVacationDays($config, $year, $until = null)
{
    if (!array_key_exists('VACATION_DAYS_AMOUNT', $config)) {
        die('Please configure VACATION_DAYS_AMOUNT in settings.php');
    }

    return $config['VACATION_DAYS_AMOUNT'] - count(getVacationDays($config, $year, $until));
}

function getAmountNotYetTakenVacation($config, $year)
{
    return getAmountNotPlanedVacationDays($config, $year, new DateTime('today'));
}

printHours('today', 'today', $config);
printHours('yesterday', 'yesterday', $config);
printHours('last Sunday', 'last Sunday +6 days', $config);
printHours('last Sunday -1 week', 'last Sunday -1 week +6 days', $config);

$total = 0;
$thisYear = (int) date('Y');

for ($year = $config['START_YEAR']; $year <= $thisYear; $year++) {
    if ($year === $thisYear) {
        $total += printHours('01.01.'.$year, 'yesterday', $config);
    } else {
        $total += printHours('01.01.'.$year, '31.12.'.$year, $config);
    }
    printVacationInfo($config, $year);
}

echo "\nTotal hours: ";
printf("%01.2f \n\n", $total);

