<?php
/**
 * Canonical assessment default structure for HAM plugin.
 * This is the single source of truth for all assessment default values.
 */

define('HAM_ASSESSMENT_DEFAULT_STRUCTURE', array(
    'anknytning' => array(
        'title' => 'Anknytning',
        'questions' => array(
            'a1' => array(
                'text' => 'Närvaro',
                'options' => array(
                    array('value' => '1', 'label' => 'Kommer inte till skolan', 'stage' => 'ej'),
                    array('value' => '2', 'label' => 'Kommer till skolan, ej till lektion', 'stage' => 'ej'),
                    array('value' => '3', 'label' => 'Kommer till min lektion ibland', 'stage' => 'trans'),
                    array('value' => '4', 'label' => 'Kommer alltid till min lektion', 'stage' => 'trans'),
                    array('value' => '5', 'label' => 'Kommer till andras lektioner', 'stage' => 'full'),
                ),
            ),
            'a2' => array(
                'text' => 'Dialog 1 - introvert',
                'options' => array(
                    array('value' => '1', 'label' => 'Helt tyst', 'stage' => 'ej'),
                    array('value' => '2', 'label' => 'Säger enstaka ord till mig', 'stage' => 'ej'),
                    array('value' => '3', 'label' => 'Vi pratar ibland', 'stage' => 'trans'),
                    array('value' => '4', 'label' => 'Har full dialog med mig', 'stage' => 'trans'),
                    array('value' => '5', 'label' => 'Har dialog med andra vuxna', 'stage' => 'full'),
                ),
            ),
            'a3' => array(
                'text' => 'Dialog 2 - extrovert',
                'options' => array(
                    array('value' => '1', 'label' => 'Pratar oavbrutet', 'stage' => 'ej'),
                    array('value' => '2', 'label' => 'Är tyst vid tillsägelse', 'stage' => 'ej'),
                    array('value' => '3', 'label' => 'Lyssnar på mig', 'stage' => 'trans'),
                    array('value' => '4', 'label' => 'Har full dialog med mig', 'stage' => 'trans'),
                    array('value' => '5', 'label' => 'Dialog med vissa andra vuxna', 'stage' => 'full'),
                ),
            ),
            'a4' => array(
                'text' => 'Blick, kroppsspråk',
                'options' => array(
                    array('value' => '1', 'label' => 'Möter inte min blick', 'stage' => 'ej'),
                    array('value' => '2', 'label' => 'Har gett mig ett ögonkast', 'stage' => 'ej'),
                    array('value' => '3', 'label' => 'Håller fast ögonkontakt ', 'stage' => 'trans'),
                    array('value' => '4', 'label' => 'Pratar” med ögonen', 'stage' => 'trans'),
                    array('value' => '5', 'label' => 'Möter andras blickar', 'stage' => 'full'),
                ),
            ),
            'a5' => array(
                'text' => 'Beröring',
                'options' => array(
                    array('value' => '1', 'label' => 'Jag får inte närma mig', 'stage' => 'ej'),
                    array('value' => '2', 'label' => 'Jag får närma mig', 'stage' => 'ej'),
                    array('value' => '3', 'label' => 'Tillåter beröring av mig', 'stage' => 'trans'),
                    array('value' => '4', 'label' => 'Söker fysisk kontakt, ex. kramar', 'stage' => 'trans'),
                    array('value' => '5', 'label' => 'Tillåter beröring av andra vuxna', 'stage' => 'full'),
                ),
            ),
            'a6' => array(
                'text' => 'Vid konflikt',
                'options' => array(
                    array('value' => '1', 'label' => 'Försvinner från skolan vid konflikt', 'stage' => 'ej'),
                    array('value' => '2', 'label' => 'Stannar kvar på skolan', 'stage' => 'ej'),
                    array('value' => '3', 'label' => 'Kommer tillbaka till mig', 'stage' => 'trans'),
                    array('value' => '4', 'label' => 'Förklarar för mig efter konikt', 'stage' => 'trans'),
                    array('value' => '5', 'label' => 'Kommer tillbaka till andra vuxna', 'stage' => 'full'),
                ),
            ),
            'a7' => array(
                'text' => 'Förtroende',
                'options' => array(
                    array('value' => '1', 'label' => 'Delar inte med sig till mig', 'stage' => 'ej'),
                    array('value' => '2', 'label' => 'Delar med sig till mig ibland', 'stage' => 'ej'),
                    array('value' => '3', 'label' => 'Vill dela med sig till mig', 'stage' => 'trans'),
                    array('value' => '4', 'label' => 'Ger mig förtroenden', 'stage' => 'trans'),
                    array('value' => '5', 'label' => 'Ger även förtroenden till vissa andra', 'stage' => 'full'),
                ),
            ),
        ),
        'comments' => array(),
    ),
    'ansvar' => array(
        'title' => 'Ansvar',
        'questions' => array(
            'b1' => array(
                'text' => 'Impulskontroll',
                'options' => array(
                    array('value' => '1', 'label' => 'Helt impulsstyrd. Ex. kan inte sitta still, förstör, säger fula ord', 'stage' => 'ej'),
                    array('value' => '2', 'label' => 'Kan ibland hålla negativa känslor utan att agera på dem', 'stage' => 'ej'),
                    array('value' => '3', 'label' => 'Skäms över negativa beteenden', 'stage' => 'trans'),
                    array('value' => '4', 'label' => 'Kan ta emot tillsägelse', 'stage' => 'trans'),
                    array('value' => '5', 'label' => 'Kan prata om det som hänt', 'stage' => 'full'),
                ),
            ),
            'b2' => array(
                'text' => 'Förberedd',
                'options' => array(
                    array('value' => '1', 'label' => 'Aldrig', 'stage' => 'ej'),
                    array('value' => '2', 'label' => 'Lyckas vara förberedd en första gång', 'stage' => 'ej'),
                    array('value' => '3', 'label' => 'Försöker vara förberedd som andra', 'stage' => 'trans'),
                    array('value' => '4', 'label' => 'Pratar om förberedelse', 'stage' => 'trans'),
                    array('value' => '5', 'label' => 'Planerar och har ordning', 'stage' => 'full'),
                ),
            ),
            'b3' => array(
                'text' => 'Fokus',
                'options' => array(
                    array('value' => '1', 'label' => 'Kan inte fokusera', 'stage' => 'ej'),
                    array('value' => '2', 'label' => 'Kan fokusera en kort stund vid enskild tillsägelse', 'stage' => 'ej'),
                    array('value' => '3', 'label' => 'Kan fokusera självmant tillsammans med andra', 'stage' => 'trans'),
                    array('value' => '4', 'label' => 'Pratar om fokus och förbättrar sig', 'stage' => 'trans'),
                    array('value' => '5', 'label' => 'Kan fokusera och koncentrera sig', 'stage' => 'full'),
                ),
            ),
            'b4' => array(
                'text' => 'Turtagning',
                'options' => array(
                    array('value' => '1', 'label' => 'Klarar ej', 'stage' => 'ej'),
                    array('value' => '2', 'label' => 'Klarar av att vänta vid tillsägelse', 'stage' => 'ej'),
                    array('value' => '3', 'label' => 'Gör som andra, räcker upp handen', 'stage' => 'trans'),
                    array('value' => '4', 'label' => 'Kan komma överens om hur turtagning fungerar', 'stage' => 'trans'),
                    array('value' => '5', 'label' => 'Full turtagning andra', 'stage' => 'full'),
                ),
            ),
            'b5' => array(
                'text' => 'Instruktion',
                'options' => array(
                    array('value' => '1', 'label' => 'Tar inte/förstår inte instruktion', 'stage' => 'ej'),
                    array('value' => '2', 'label' => 'Tar/förstår instruktion i ett led men startar inte uppgift', 'stage' => 'ej'),
                    array('value' => '3', 'label' => 'Tar/förstår instruktion i flera led, kan lösa uppgift ibland', 'stage' => 'trans'),
                    array('value' => '4', 'label' => 'Kan prata om uppgiftslösning', 'stage' => 'trans'),
                    array('value' => '5', 'label' => 'Genomför uppgifter', 'stage' => 'full'),
                ),
            ),
            'b6' => array(
                'text' => 'Arbeta själv',
                'options' => array(
                    array('value' => '1', 'label' => 'Klara inte', 'stage' => 'ej'),
                    array('value' => '2', 'label' => 'Löser en uppgift med stöd', 'stage' => 'ej'),
                    array('value' => '3', 'label' => 'Kan klara uppgifter självständigt i klassrummet', 'stage' => 'trans'),
                    array('value' => '4', 'label' => 'Gör ofta läxor och pratar om dem', 'stage' => 'trans'),
                    array('value' => '5', 'label' => 'Tar ansvar för självständigt arbete utanför skolan', 'stage' => 'full'),
                ),
            ),
        ),
        'comments' => array(),
    ),
));
