<?php

$config = [
    'TOGGL_API_TOKEN'       => '',      // your toggl API Token
    'TOGGL_USER_AGENT'      => '',      // your toggl username (goTom-email)
    'TOGGL_USER_IDS'        => '',      // your toggl id from profile.json
    'DISPLAY_DATE_FORMAT'   => '',      // optional, a format to display the dates on readout e.g. 'Y-m-d' or 'd.m.Y', etc.
    'START_YEAR'            => 2020,    // optional, the year you started (2017 by default)
    'START_MONTH'           => 1,       // optional, the month you started (1 by default)
    'DAYS_OFF'              => [],      // to configure part time work, weekly days off, enter the day number for each day as array entry (1 = monday, 2 = tuesday, ...)
    'HALF_DAYS_OFF'         => [],      // to configure part time work, weekly half days off, enter the day number for each day as array entry (1 = monday, 2 = tuesday, ...)
    'VACATION_DAYS_AMOUNT'  => 5*5,     // 5 weeks à 5 days
    'VACATION'              => [    // one entry for each year
                                    2020 => [
                                        [   // one entry for each vacation in year
                                            'FROM' => null,  //Datetime, e.g.new DateTime('1-1-2020');
                                            'UNTIL' => null, //timestamp, e.g. new DateTime('2-1-2020');
                                        ],
                                    ],
                                ],
    'OTHER-LEAVE'            => [  // same format as vacation, for military service, unpaid leave, etc. (other then paid vacation)
                                ],
];
