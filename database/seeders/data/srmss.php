<?php

/*
|--------------------------------------------------------------------------
| Demo data for two SLTB-style depots
|--------------------------------------------------------------------------
| Stop coordinates are approximate town centres taken from OpenStreetMap.
| Route: [route_no, name, origin, destination, km, minutes, service, min seats,
|         recurrence, weekdays|month days, departures[], stops[[name, lat, lng]]]
*/

return [
    'MHR' => [
        'depot' => ['code' => 'MHR', 'name' => 'Maharagama Depot', 'location' => 'High Level Road, Maharagama', 'phone' => '0112850321', 'latitude' => 6.8480, 'longitude' => 79.9265],
        'routes' => [
            ['138', 'Pettah – Kottawa via Nugegoda', 'Pettah', 'Kottawa', 18.4, 65, 'normal', 50, 'daily', [],
                ['05:30', '06:15', '07:00', '07:45', '08:30', '12:00', '16:30', '17:15', '18:00'],
                [['Pettah', 6.9366, 79.8500], ['Town Hall', 6.9167, 79.8636], ['Borella', 6.9147, 79.8778], ['Nugegoda', 6.8649, 79.8997], ['Maharagama', 6.8480, 79.9265], ['Kottawa', 6.8412, 79.9650]]],
            ['122', 'Colombo – Ratnapura via Avissawella', 'Colombo Fort', 'Ratnapura', 101.0, 180, 'normal', 45, 'daily', [],
                ['05:00', '07:30', '13:00'],
                [['Colombo Fort', 6.9344, 79.8428], ['Kirulapone', 6.8780, 79.8770], ['Nugegoda', 6.8649, 79.8997], ['Maharagama', 6.8480, 79.9265], ['Homagama', 6.8441, 80.0026], ['Hanwella', 6.9012, 80.0852], ['Avissawella', 6.9543, 80.2046], ['Eheliyagoda', 6.8486, 80.2655], ['Kuruwita', 6.7768, 80.3655], ['Ratnapura', 6.6828, 80.3992]]],
            ['122', 'Poya-day pilgrim service to Ratnapura', null, null, null, null, null, null, 'monthly', [1, 15],
                ['09:30'], []],
            ['120', 'Pettah – Horana via Piliyandala', 'Pettah', 'Horana', 41.2, 105, 'normal', 0, 'daily', [],
                ['06:00', '09:00', '14:30', '17:30'],
                [['Pettah', 6.9366, 79.8500], ['Wellawatte', 6.8747, 79.8607], ['Kohuwala', 6.8669, 79.8846], ['Boralesgamuwa', 6.8406, 79.9017], ['Piliyandala', 6.8018, 79.9227], ['Kesbewa', 6.7952, 79.9408], ['Horana', 6.7159, 80.0626]]],
            ['177', 'Kollupitiya – Kaduwela office service', 'Kollupitiya', 'Kaduwela', 20.3, 70, 'normal', 0, 'weekly', [1, 2, 3, 4, 5],
                ['06:40', '07:20', '17:00', '17:40'],
                [['Kollupitiya', 6.9114, 79.8497], ['Borella', 6.9147, 79.8778], ['Rajagiriya', 6.9094, 79.8960], ['Battaramulla', 6.9020, 79.9180], ['Malabe', 6.9047, 79.9583], ['Kaduwela', 6.9353, 79.9842]]],
            ['EX1', 'Makumbura – Galle (Southern Expressway)', 'Makumbura', 'Galle', 116.0, 95, 'express', 0, 'daily', [],
                ['06:00', '10:00', '15:00'],
                [['Makumbura MMC', 6.8406, 79.9790], ['Kahathuduwa', 6.7830, 79.9990], ['Gelanigama', 6.7166, 80.0333], ['Dodangoda', 6.5540, 80.0640], ['Welipenna', 6.4636, 80.0846], ['Kurundugahahetekma', 6.2669, 80.1347], ['Galle', 6.0535, 80.2210]]],
        ],
        // [registration, fleet no, make, model, year, seats, service, base km]
        'buses' => [
            ['NB-4521', 'MHR-01', 'Ashok Leyland', 'Viking', 2016, 54, 'normal', 412300],
            ['NB-6732', 'MHR-02', 'Ashok Leyland', 'Viking', 2017, 54, 'normal', 365800],
            ['NC-1184', 'MHR-03', 'Ashok Leyland', 'Viking', 2018, 54, 'normal', 298400],
            ['NC-2290', 'MHR-04', 'Tata', 'Marcopolo LP 1618', 2018, 52, 'normal', 276150],
            ['NC-7731', 'MHR-05', 'Ashok Leyland', 'Viking', 2015, 54, 'normal', 455900],
            ['ND-0563', 'MHR-06', 'Ashok Leyland', 'Viking', 2019, 54, 'normal', 214700],
            ['ND-3318', 'MHR-07', 'Tata', 'Marcopolo LP 1618', 2019, 52, 'normal', 198200],
            ['ND-8842', 'MHR-08', 'Ashok Leyland', 'Lynx', 2020, 35, 'normal', 152600],
            ['NE-1206', 'MHR-09', 'Ashok Leyland', 'Viking', 2021, 54, 'normal', 118900],
            ['NE-4475', 'MHR-10', 'Ashok Leyland', 'Viking', 2021, 54, 'normal', 102300],
            ['NE-6619', 'MHR-11', 'Tata', 'Starbus', 2022, 50, 'normal', 76400],
            ['NA-9921', 'MHR-12', 'Ashok Leyland', 'Viking', 2012, 54, 'normal', 610200],
            ['NG-2201', 'MHR-X1', 'Yutong', 'ZK6122H9', 2022, 49, 'express', 188300],
            ['NG-2202', 'MHR-X2', 'Yutong', 'ZK6122H9', 2022, 49, 'express', 182900],
            ['NG-3415', 'MHR-X3', 'King Long', 'XMQ6127', 2023, 45, 'express', 96100],
        ],
        // [employee no, name, phone, licence expiry offset (days), status]
        'drivers' => [
            ['MHR-D101', 'K. A. Sunil Perera', '0771234501', 900, 'active'],
            ['MHR-D102', 'W. M. Nimal Bandara', '0712345602', 640, 'active'],
            ['MHR-D103', 'H. P. Chaminda Silva', '0763456703', 410, 'active'],
            ['MHR-D104', 'R. M. Ajith Kumara', '0704567804', 18, 'active'],
            ['MHR-D105', 'S. Rajendran', '0775678905', 1200, 'active'],
            ['MHR-D106', 'M. I. Mohamed Rizwan', '0726789006', 760, 'active'],
            ['MHR-D107', 'D. G. Lasantha Fernando', '0777890107', 530, 'active'],
            ['MHR-D108', 'A. K. Ruwan Jayawardena', '0718901208', 300, 'active'],
            ['MHR-D109', 'P. L. Saman Kumara', '0769012309', 1000, 'active'],
            ['MHR-D110', 'J. A. Priyantha de Silva', '0750123410', 220, 'active'],
            ['MHR-D111', 'N. Sivakumar', '0771122311', 870, 'active'],
            ['MHR-D112', 'T. D. Mahesh Rathnayake', '0712233412', 480, 'active'],
            ['MHR-D113', 'G. H. Kamal Gunasekara', '0763344513', -6, 'active'],
            ['MHR-D114', 'U. K. Tharindu Madushanka', '0704455614', 1300, 'active'],
            ['MHR-D115', 'B. M. Nuwan Pradeep', '0775566715', 700, 'on_leave'],
            ['MHR-D116', 'L. P. Susantha Wickramasinghe', '0726677816', 950, 'active'],
        ],
    ],

    'KDY' => [
        'depot' => ['code' => 'KDY', 'name' => 'Kandy South Depot', 'location' => 'Peradeniya Road, Kandy', 'phone' => '0812223344', 'latitude' => 7.2906, 'longitude' => 80.6337],
        'routes' => [
            ['1', 'Kandy – Colombo via Kegalle', 'Kandy', 'Colombo Fort', 116.0, 210, 'semi_luxury', 40, 'daily', [],
                ['05:30', '08:00', '13:30'],
                [['Kandy', 7.2906, 80.6337], ['Peradeniya', 7.2690, 80.5970], ['Kadugannawa', 7.2547, 80.5243], ['Mawanella', 7.2528, 80.4467], ['Kegalle', 7.2513, 80.3464], ['Warakapola', 7.2266, 80.1966], ['Nittambuwa', 7.1442, 80.0950], ['Kadawatha', 7.0010, 79.9530], ['Colombo Fort', 6.9344, 79.8428]]],
            ['655', 'Kandy – Gampola via Peradeniya', 'Kandy', 'Gampola', 24.0, 60, 'normal', 0, 'daily', [],
                ['06:00', '07:30', '12:00', '16:30', '18:00'],
                [['Kandy', 7.2906, 80.6337], ['Peradeniya', 7.2690, 80.5970], ['Gelioya', 7.2136, 80.6017], ['Gampola', 7.1643, 80.5696]]],
        ],
        'buses' => [
            ['NB-7710', 'KDY-01', 'Ashok Leyland', 'Viking', 2017, 54, 'normal', 344500],
            ['NC-5521', 'KDY-02', 'Ashok Leyland', 'Viking', 2018, 54, 'normal', 287300],
            ['ND-2290', 'KDY-03', 'Tata', 'Marcopolo LP 1618', 2019, 52, 'normal', 205100],
            ['NF-1102', 'KDY-S1', 'Ashok Leyland', 'Viking Semi-Luxury', 2021, 47, 'semi_luxury', 162400],
            ['NF-1103', 'KDY-S2', 'Ashok Leyland', 'Viking Semi-Luxury', 2021, 47, 'semi_luxury', 158800],
            ['NF-2240', 'KDY-S3', 'Tata', 'Starbus Ultra', 2022, 45, 'semi_luxury', 121700],
        ],
        'drivers' => [
            ['KDY-D201', 'Y. M. Gamini Dissanayake', '0812345601', 800, 'active'],
            ['KDY-D202', 'E. M. Tikiri Banda', '0772345602', 560, 'active'],
            ['KDY-D203', 'R. Ganeshan', '0712345603', 1100, 'active'],
            ['KDY-D204', 'K. W. Asanka Herath', '0762345604', 380, 'active'],
            ['KDY-D205', 'M. N. Fazil Ahamed', '0702345605', 690, 'active'],
            ['KDY-D206', 'P. G. Upul Senanayake', '0752345606', 25, 'active'],
        ],
    ],
];
