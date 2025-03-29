# Stegetföre HAM projektplan

Projekt: Headless Access Manager (HAM) WordPress Plugin
Mål: Skapa ett WordPress-plugin som fungerar som en backend (API + Admin) för en Next.js frontend. Pluginet ska hantera användarroller, behörigheter, formulärdata (elevbedömningar) och tillhandahålla API-endpoints för datahämtning och statistik på olika nivåer (elev, klass, lärare, skola, skolchef).
Tekniska Val:
* Backend: WordPress Plugin
* API: WordPress REST API
* Autentisering: JWT (JSON Web Tokens) för API-kommunikation med Next.js. Vi behöver en pålitlig JWT-implementation (antingen en tredjeparts WP-plugin eller ett PHP-bibliotek via Composer).
* Datastruktur:
    * Custom User Roles (Elev, Lärare, Rektor, Skolchef)
    * Custom Post Types (CPT): Assessment, School, Class
    * User Meta: För att koppla användare till skolor, klasser, etc.
    * Assessment Data: Lagras i Assessment CPT, troligen med svar och kommentarer som JSON eller serialiserad data i post meta.

Utförlig Projektplan
Fas 1: Grundläggande Struktur och Datamodellering (Backend)
1. Plugin Setup:
    * Skapa huvudpluginfil (headless-access-manager.php) med header, konstanter (för sökvägar, slugs etc.).
    * Skapa grundläggande filstruktur ( inc/, admin/, api/, core/, helpers/).
    * Implementera aktiverings- och deaktiveringshooks (inc/activation.php, inc/deactivation.php) – t.ex. för att rensa rewrite rules.
2. Användarroller och Behörigheter:
    * Definiera custom roles: student, teacher, principal, school_head (inc/core/roles.php).
    * Definiera custom capabilities: submit_assessment, view_own_stats, view_class_stats, view_teacher_stats, view_school_stats, view_multi_school_stats, manage_school_users, manage_school_classes, manage_schools, etc. (inc/core/capabilities.php).
    * Tilldela capabilities till respektive roll vid aktivering (eller via roles.php). Se till att högre roller ärver lägre rollers relevanta capabilities.
3. Custom Post Types (CPTs):
    * Registrera CPT School (inc/core/post-types.php). Fält: Skolnamn.
    * Registrera CPT Class (inc/core/post-types.php). Fält: Klassnamn. Koppling till School (t.ex. via post meta eller taxonomi).
    * Registrera CPT Assessment (inc/core/post-types.php).
        * Fält: post_author (Läraren som skapade), Timestamp (post_date).
        * Post Meta: _student_id (Användar-ID för eleven), _assessment_data (JSON/serialiserad array med svar och kommentarer), _assessment_date (Specifikt datum om det skiljer sig från post_date).
4. Användarmeta och Relationer:
    * Definiera user meta-fält (inc/core/user-meta.php):
        * Lärare: _school_id, _class_ids (array av klass-ID:n).
        * Elev: _class_ids (array av klass-ID:n). (En elev kan gå i flera "ämnesklasser" med olika lärare).
        * Rektor: _school_id.
        * Skolchef: _managed_school_ids (array av skol-ID:n).
    * Skapa funktioner i inc/helpers/utilities.php för att enkelt hämta/uppdatera dessa relationer.
5. Admin Gränssnitt (Grundläggande):
    * Skapa grundläggande admin-menyer för åtkomst till CPTs (inc/admin/admin-menu.php). Begränsa synlighet baserat på roll.
    * Lägg till custom fält i användarprofilen för att hantera relationer (Skola, Klasser) (inc/admin/class-ham-user-profile.php). Gör fälten synliga/redigerbara baserat på den inloggade administratörens roll och den redigerade användarens roll.
Fas 2: API - Autentisering och Grundläggande Datahantering
1. JWT Autentisering:
    * Integrera ett JWT-bibliotek (t.ex. firebase/php-jwt via Composer) eller en WP JWT-plugin.
    * Skapa API endpoint för inloggning (POST /wp-json/jwt-auth/v1/token eller motsvarande) som returnerar en JWT vid korrekta WP-inloggningsuppgifter (inc/api/class-ham-auth-controller.php).
    * Implementera mekanism för att validera JWT på skyddade API-endpoints.
2. API Endpoints - Användare:
    * Endpoint för att hämta info om aktuell inloggad användare (GET /wp-json/ham/v1/users/me) (inc/api/class-ham-users-controller.php). Returnerar användarinfo + roller, capabilities och tillhörande skola/klasser.
    * Endpoints för att lista/hantera användare (för admin/rektor/skolchef):
        * GET /wp-json/ham/v1/users (med filter för roll, skola, klass).
        * POST /wp-json/ham/v1/users (för att skapa användare - kräver hög behörighet).
        * PUT /wp-json/ham/v1/users/{id} (för att uppdatera användare).
        * Dessa kräver noggranna behörighetskontroller baserade på den anropande användarens roll och scope (t.ex. en rektor kan bara hantera användare på sin skola).
3. API Endpoints - Grunddata (Skolor, Klasser):
    * Endpoints för att lista skolor (GET /wp-json/ham/v1/schools) och klasser (GET /wp-json/ham/v1/classes) med filtermöjligheter (inc/api/class-ham-data-controller.php - eller separata). Kräver behörighetskontroller (t.ex. lärare ser sina klasser, rektor ser skolans klasser).
4. API Endpoint - Bedömningsformulär:
    * Skapa endpoint för att ta emot ifyllt formulär (POST /wp-json/ham/v1/assessments) (inc/api/class-ham-assessments-controller.php).
        * Input: student_id, assessment_data (svar + kommentarer).
        * Logik: Validera input, verifiera att läraren (från JWT) har behörighet att bedöma eleven (t.ex. via gemensam klass), skapa en ny Assessment CPT-post.
        * Kräver submit_assessment capability.
Fas 3: API - Statistik och Rapportering
* Planering: Definiera exakt vilka statistiska mått som ska beräknas (medelvärden per fråga, utveckling över tid, antal bedömningar, etc.). Detta påverkar hur data hämtas och bearbetas.
* Implementation: (inc/api/class-ham-stats-controller.php och inc/helpers/stats-helpers.php)
    1. Elevstatistik:
        * GET /wp-json/ham/v1/stats/student/{student_id}/progress
        * Input: student_id. Behörighet: Eleven själv, lärare kopplad till eleven, rektor på elevens skola, skolchef.
        * Output: Tidsstämplad data från elevens alla Assessment-poster, ev. aggregerad/bearbetad.
    2. Klassstatistik:
        * GET /wp-json/ham/v1/stats/class/{class_id}
        * Input: class_id. Behörighet: Lärare kopplad till klassen, rektor på skolans klass, skolchef.
        * Output: Aggregerad statistik för alla elever i klassen (medelvärden, distributioner etc.).
    3. Lärarstatistik:
        * GET /wp-json/ham/v1/stats/teacher/{teacher_id}
        * Input: teacher_id. Behörighet: Läraren själv, rektor på lärarens skola, skolchef.
        * Output: Statistik över bedömningar gjorda av läraren (antal, genomsnittlig frekvens, ev. aggregerad data från bedömningarna).
    4. Skolstatistik:
        * GET /wp-json/ham/v1/stats/school/{school_id}
        * Input: school_id. Behörighet: Rektor på skolan, skolchef.
        * Output: Aggregerad statistik för skolan (elevprestationer, läraraktivitet, klassjämförelser).
    5. Skolchefsstatistik (Multi-skola):
        * GET /wp-json/ham/v1/stats/schools (Ev. med query params för specifika skolor)
        * Behörighet: Skolchef.
        * Output: Aggregerad statistik över de skolor skolchefen hanterar (jämförelser mellan skolor, övergripande trender).
* Statistikberäkning: Skapa robusta funktioner i inc/helpers/stats-helpers.php som hämtar Assessment-data och utför nödvändiga beräkningar. Dessa anropas sedan av API-kontrollerna. Optimera databasfrågor för prestanda.
Fas 4: Admin - Utökad Funktionalitet och Användarhantering
1. Förbättrad Användarhantering:
    * Utveckla admin-gränssnitt (inom WP Admin) där Rektorer och Skolchefer kan:
        * Skapa nya användare (Lärare, Elever) och tilldela roller.
        * Koppla användare till sin skola/sina skolor.
        * Koppla lärare och elever till klasser inom skolan.
    * Använd WP_List_Table eller metaboxar för att visa och hantera relationer.
    * Implementera strikta behörighetskontroller så att administratörer bara kan hantera det de har rätt till.
2. Datavyer (Admin):
    * Skapa listvyer för Assessment-poster i WP Admin, filtrerbara per elev, lärare, klass, datum. Primärt för felsökning och administration.
    * (Valfritt) Visa enkel, grundläggande statistik direkt i WP Admin för snabb överblick (t.ex. antal bedömningar per lärare/klass).
Fas 5: Testning, Säkerhet och Refaktorering
1. API-Testning:
    * Använd verktyg som Postman för att noggrant testa alla API-endpoints med olika användarroller och data. Verifiera:
        * Korrekt data returneras.
        * Behörighetskontroller fungerar som de ska (obehöriga nekas åtkomst).
        * Felhantering är korrekt (t.ex. 401, 403, 404, 500-fel).
        * Inputvalidering fungerar.
2. Säkerhetsgranskning:
    * Säkerställ att all input saneras och all output escapas korrekt.
    * Granska alla behörighetskontroller (current_user_can()).
    * Använd nonces där det är relevant (främst i WP Admin-formulär).
    * Se över JWT-implementationens säkerhet (nycklar, giltighetstid).
3. Kodkvalitet och Refaktorering:
    * Gå igenom koden och se till att filer håller sig under 300 rader. Bryt ut logik till mindre funktioner eller klasser vid behov.
    * Använd inc/helpers/ för återanvändbara funktioner.
    * Följ WordPress Coding Standards.
    * Lägg till inline-kommentarer och dokumentationsblock.
4. Prestandaoptimering:
    * Analysera långsamma databasfrågor (speciellt för statistik) och optimera dem (index, transienter för cachning av resultat?).
5. Dokumentation:
    * Färdigställ readme.txt med installationsinstruktioner, beskrivning av funktioner och API-endpoints.
    * Skapa separat API-dokumentation för Next.js-utvecklaren (kan genereras med verktyg eller skrivas manuellt).
Fas 6: Next.js Integration (Support)
* Tillhandahålla tydlig API-dokumentation.
* Vara tillgänglig för att felsöka API-anrop från Next.js-applikationen.
* Eventuellt justera API-endpoints baserat på feedback från frontend-utvecklingen.







# TREE:
wp-content/plugins/headless-access-manager/
├── headless-access-manager.php   # Huvudfil, laddar allt annat
├── readme.txt
├── composer.json                 # För PHP-beroenden (t.ex. JWT)
├── vendor/                       # Composer-beroenden
│   └── autoload.php
└── inc/
    ├── activation.php            # Kod som körs vid plugin-aktivering
    ├── deactivation.php          # Kod som körs vid plugin-deaktivering
    ├── constants.php             # Definierar konstanter
    ├── loader.php                # Inkluderar nödvändiga filer i rätt ordning
    │
    ├── core/                     # Kärnfunktionalitet (Datastruktur, Roller)
    │   ├── roles.php             # Registrerar anpassade roller
    │   ├── capabilities.php      # Definierar och mappar capabilities
    │   ├── post-types.php        # Registrerar CPTs (Assessment, School, Class)
    │   ├── taxonomies.php        # (Valfritt) Registrerar taxonomier
    │   └── user-meta.php         # Hanterar custom user meta fields
    │
    ├── api/                      # REST API-specifik kod
    │   ├── api-loader.php        # Registrerar alla API-routes och klasser
    │   ├── class-ham-base-controller.php # Basklass för API-kontrollers (validering, behörighet)
    │   ├── class-ham-auth-controller.php # Hanterar autentisering (JWT)
    │   ├── class-ham-users-controller.php # Endpoints för användardata (/users)
    │   ├── class-ham-data-controller.php  # Endpoints för grunddata (schools, classes)
    │   ├── class-ham-assessments-controller.php # Endpoints för bedömningar (/assessments)
    │   └── class-ham-stats-controller.php # Endpoints för statistik (/stats/*)
    │       # (Kan delas upp ytterligare vid behov, t.ex. class-ham-stats-student-controller.php)
    │
    ├── admin/                    # WP Admin-specifik kod
    │   ├── admin-loader.php      # Laddar admin-specifika funktioner
    │   ├── admin-menu.php        # Skapar admin-menyer
    │   ├── class-ham-user-profile.php # Anpassar användarprofilsidan
    │   ├── class-ham-user-list.php   # (Valfritt) Anpassar användarlistan
    │   ├── class-ham-settings-page.php # (Valfritt) Inställningssida för pluginet
    │   └── meta-boxes.php        # Hanterar metaboxar för CPTs (t.ex. koppla klass till skola)
    │
    └── helpers/                  # Hjälpfunktioner
        ├── utilities.php         # Generella hjälpfunktioner (validering, datahämtning etc.)
        ├── permissions.php       # Hjälpfunktioner för behörighetskontroller
        └── stats-helpers.php     # Funktioner för att beräkna statistik
