<?php

$config = [
    'TOGGL_API_TOKEN'       => 'a454ed72422f4e2232df26f009e67c00',
    'TOGGL_USER_AGENT'      => 'marc.bachmann@gotom.io',
    'TOGGL_USER_IDS'        => '5473609',
    'DISPLAY_DATE_FORMAT'   => 'd.m.y', 
    'START_YEAR' => 2020,
    'START_MONTH' => 3,
    'HALF_DAYS_OFF' => [],
    'DAYS_OFF' => [5],  // Friday
    'VACATION_DAYS_AMOUNT'  => 10/12*5*4,     // 5 weeks à 4 days, but only 10 months (start in march)
    'VACATION' => [
        2020 => [
            [   // GLK1
                'FROM' => new DateTime('6-7-2020'),
                'UNTIL' => new DateTime('19-7-2020')
            ],
            [   // Segeltörn
                'FROM' => new DateTime('27-7-2020'),
                'UNTIL' => new DateTime('31-7-2020')
            ],
            [   // HeWu
                'FROM' => new DateTime('3-10-2020'),
                'UNTIL' => new DateTime('10-10-2020')
            ],
        ],
    ],
    'OTHER-LEAVE' => [  // military service, unpaid leave, illness, etc.
        2020 => [
            [   // Ass D CORONA 20
                'FROM' => new DateTime('17-3-2020'),
                'UNTIL' => new DateTime('1-5-2020')
            ],
            [   // Magendarmgrippe
                'FROM' => new DateTime('10-8-2020'),
                'UNTIL' => new DateTime('11-8-2020')
            ],
        ],
    ],
];

