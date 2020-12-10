#!/usr/bin/env php
<?php

const DAILY_WORK_HOURS = 8;

function getHolidays(int $year): array
{
    $easterDate = easter_date($year);
    $easterDay = date('j', $easterDate);
    $easterMonth = date('n', $easterDate);
    $easterYear = date('Y', $easterDate);

    // $bettag = strtotime('3 sunday', mktime(0, 0, 0, 9, 1, $year)); //bettag

    return [
        //  mktime(0, 0, 0, 12, 31, $year), // silvester
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
    ];
}

function getHalfHolidays(int $year): array
{
    $knabenschiessen = strtotime('2 sunday', mktime(0, 0, 0, 9, 1, $year));
    $knabenschiessen = strtotime('+1 day', $knabenschiessen);
    $heiligabend = mktime(0, 0, 0, 12, 24, $year);
    $silvester = mktime(0, 0, 0, 12, 31, $year);

    return [$silvester, $heiligabend, $knabenschiessen, getSechselauten($year)];
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

function getUserInfos(): array
{
    $user = getenv('GOTOM_USER');
    if (!$user) {
        $user = 'settings';
    }

    $config = require __DIR__.'/'.$user.'.php';
    $token = getenv('TOGGL_TOKEN');
    if ($token) {
        $config['TOGGL_API_TOKEN'] = $token;
    }

    if (!isset($config['TOGGL_API_TOKEN'])) {
        die('Create with a valid toggl token (api key) - https://toggl.com/app/profile');
    }
    if (!array_key_exists('TOGGL_USER_IDS', $config)) {
        die("set TOGGL_USER_IDS\n");
    }
    if (!array_key_exists('TOGGL_USER_AGENT', $config)) {
        $config['TOGGL_USER_AGENT'] = 'test_api';
    }

    return $config;
}

function totalHours(DateTime $sinceDate, DateTime $untilDate, $config): float
{
    $since = $sinceDate->format('Y-m-d');
    $until = $untilDate->format('Y-m-d');
    $url = 'https://toggl.com/reports/api/v2/summary?type=me&workspace_id=1006502&since='.$since.'&until='.$until.'&user_ids='.$config['TOGGL_USER_IDS'].'&user_agent='.$config['TOGGL_USER_AGENT'];
    $command = ' curl -s  -u '.$config['TOGGL_API_TOKEN'].':api_token GET "'.$url.'" ';

    $json = exec($command);
    $arr = json_decode($json, true, 512);

    return $arr['total_grand'] / 1000 / 60 / 60;
}

function hoursToWork(DateTime $since, DateTime $until, array $config): float
{
    $sinceStart = new DateTime($config['START_DATE']);

    if ($since < $sinceStart) {
        $since = $sinceStart;
    }
    $daysOff = array_key_exists('DAYS_OFF', $config) ? $config['DAYS_OFF'] : [];
    $halfDaysOff = array_key_exists('HALF_DAYS_OFF', $config) ? $config['HALF_DAYS_OFF'] : [];

    $days = 0;
    while ($since <= $until) {
        $w = (int) $since->format('w');
        if ($w !== 0 && $w !== 6 && !in_array($w, $daysOff, true)) {
            $hdays = getHolidays((int) $since->format('Y'));
            if (!in_array($since->getTimestamp(), $hdays, true)) {
                $halfDays = getHalfHolidays((int) $since->format('Y'));
                $days++;
                if (in_array($w, $halfDaysOff, true)) {
                    // current day is a weekly half day off
                    $days -= 0.5;
                }
                if (in_array($since->getTimestamp(), $halfDays, true)) {
                    // current day is not  a public holiday
                    $days -= 0.5;
                }
            }
        }
        $since->modify('+1 day');
    }

    return $days * DAILY_WORK_HOURS; // hours to work each day
}

function printHours(string $since, string $until, array $config, float $extraO = 0): float
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

    $t = totalHours(clone $sinceDatetime, clone $untilDatetime, $config);
    $w = hoursToWork(clone $sinceDatetime, clone $untilDatetime, $config);
    $v = countVacation(clone $sinceDatetime, clone $untilDatetime, $config);
    $v += countVacation(clone $sinceDatetime, clone $untilDatetime, $config, 'OTHER-LEAVE');
    $o = $t - $w + $v + $extraO;
    printf("  %01.2f - %01.2f + %01.2f = %01.2f \n", $t, $w, $v, $o);

    return $o;
}

function printVacationInfo(array $config, int $year)
{
    $since = new DateTime('01-01-'.$year);
    $today = new DateTime('today');
    $vacHoursPlanned = countVacation($since, new DateTime('31-12-'.$year), $config);
    $vacDaysPlanned = $vacHoursPlanned / DAILY_WORK_HOURS;
    $vacTaken = countVacation($since, $today, $config);
    $vacDaysSaldo = $config['VACATION_DAYS_AMOUNT'] - $vacDaysPlanned;
    $otherLeaveHours = countVacation($since, $today, $config, 'OTHER-LEAVE');
    printf("Vacation taken: %01.1fh (%01.1fd)\n", $vacTaken, $vacTaken / DAILY_WORK_HOURS);
    printf("Vacation planed: %01.1fh (%01.1fd)\n", $vacHoursPlanned, $vacDaysPlanned);
    printf("Vacation saldo: %01.1fh (%01.1fd)\n", $vacDaysSaldo * DAILY_WORK_HOURS, $vacDaysSaldo);
    printf("Other leave taken: %01.1fh (%01.1fd)\n", $otherLeaveHours, $otherLeaveHours / DAILY_WORK_HOURS);
}

function getOffArr(array $config, int $year): array
{
    $daysOff = array_key_exists('DAYS_OFF', $config) ? $config['DAYS_OFF'] : [];
    $halfDaysOff = array_key_exists('HALF_DAYS_OFF', $config) ? $config['HALF_DAYS_OFF'] : [];
    $hdays = getHolidays($year);
    $halfDays = getHalfHolidays($year);

    return [$daysOff, $halfDaysOff, $hdays, $halfDays];
}

function countVacation(DateTime $since, DateTime $until, array $config, string $confKey = 'VACATION'): float
{
    $year = (int) $since->format('Y');
    if (!array_key_exists($confKey, $config) || !array_key_exists($year, $config[$confKey])) {
        return 0;
    }
    $vacationHours = 0;
    $vacations = $config[$confKey][$year];
    //fallback
    if ($vacations[0] === 'sum') {
        array_shift($vacations);

        return array_sum($vacations) * DAILY_WORK_HOURS;
    }
    [$daysOff, $halfDaysOff, $hdays, $halfDays] = getOffArr($config, $year);
    foreach ($config[$confKey][$year] as $vacation) {
        if (!array_key_exists('FROM', $vacation) || !array_key_exists('UNTIL', $vacation)) {
            die("vacation not configured correctly with 'FROM' and 'UNTIL'\n");
        }
        if ($vacation['FROM'] === null || $vacation['UNTIL'] === null) {
            break;
        }
        $from = clone $vacation['FROM'];
        while ($from <= $vacation['UNTIL']) {
            if ($from >= $since && $from <= $until) {
                $vacationHours += hoursToWorkAtDay($hdays, $halfDays, $daysOff, $halfDaysOff, $from);
            }
            $from->modify('+1 day');
        }
    }

    return $vacationHours;
}

function getOtherLeaveDays($config, $year, $until = null): array
{
    if (!array_key_exists('OTHER-LEAVE', $config) || !array_key_exists($year, $config['OTHER-LEAVE'])) {
        return [];
    }
    [$daysOff, $halfDaysOff, $hdays, $halfDays] = getOffArr($config, $year);
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
                if (hoursToWorkAtDay($hdays, $halfDays, $daysOff, $halfDaysOff, $from) > 0) {
                    $otherLeaveDays[] = clone $from;
                }
            }
            $from->modify('+1 day');
        }
    }

    return $otherLeaveDays;
}

function hoursToWorkAtDay(array $holidays, array $halfHolidays, array $daysOff, array $halfDaysOff, DateTime $day): int
{
    $daysOff[] = 0;
    $daysOff[] = 6;
    $w = (int) $day->format('w');
    if (in_array($w, $daysOff, false)) {
        return 0;
    }

    if (in_array($day->getTimestamp(), $holidays, true)) {
        // no hours on public holidays
        return 0;
    }

    // hours to work on a "normal" day
    $hours = DAILY_WORK_HOURS;
    if (in_array($day->getTimestamp(), $halfHolidays, true)) {
        // 4 hours to work on a half a public holiday
        $hours -= 4;
    }
    if (in_array($day->getTimestamp(), $halfDaysOff, true)) {
        // no hours to work on a half day off on a half public holiday
        $hours -= 4;
    }

    return $hours;

}

echo "\n";
$config = getUserInfos();

$displayDate = 'today';
if (2 === $argc && strtotime($argv[1])) {
    $displayDate = $argv[1];
}

$displayDateMin1 = $displayDate.' -1 day';

printHours($displayDate, $displayDate, $config);
printHours($displayDateMin1, $displayDateMin1, $config);
printHours('last Sunday', 'last Sunday +6 days', $config);
printHours('last Sunday -1 week', 'last Sunday -1 week +6 days', $config);

$total = 0;
$thisYear = (int) date('Y');
$startYear = (int) (new DateTimeImmutable($config['START_DATE']))->format('Y');
for ($year = $startYear; $year <= $thisYear; $year++) {
    if ($year === $thisYear) {
        $total += printHours('01.01.'.$year, 'yesterday', $config);
    } else {
        $total += printHours('01.01.'.$year, '31.12.'.$year, $config);
    }
}

printf("\nOverall: %01.2fh / %01.2fd \n\n", $total, $total / DAILY_WORK_HOURS);

printVacationInfo($config, date('Y'));
