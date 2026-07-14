# Smart Community Help App — Installation Guide

A Mobile Cloud Computing project: **Flutter** mobile app + **Laravel 12 REST API** backend +
**MySQL** database. People can post a **help offer** (e.g. "free ride to hospital
appointments", "spare wheelchair to lend", "volunteers needed for food bank") and others can
**request** it. Includes real-time chat (Laravel Reverb), ratings, push notifications
(OneSignal), image upload (Cloudinary), and maps/routing (OpenStreetMap).

> **History note:** this project started as a "Smart Food Donation App" and was pivoted to
> the broader "Smart Community Help" concept mid-build (another group had already built a
> similar food-donation system). The backend/database and most of the Flutter app were
> renamed accordingly — `donations` → `help_offers`, `claims` → `assistance_requests`,
> `donor`/`receiver` roles → `helper`/`requester`. A few internal, non-user-facing
> identifiers were deliberately left as-is to limit risk/scope for a working demo:
> - The Flutter package name is still `food_donation_app` (folder `food_donation_app`,
>   Dart package `food_donation_app`) — purely an internal identifier, invisible to users.
>   The **displayed** app name/label is "Smart Community Help" everywhere that matters
>   (splash screen, `MaterialApp.title`, Android app label).
> - The Laravel folder is still `food-donation` and its Laragon virtual host is still
>   `food-donation.test` — renaming a live folder path risks breaking references; if you
>   want a clean rename, see step 7 below.

> **Also note:** the original brief listed "Google Maps API" for location. This build uses
> OpenStreetMap + `flutter_map` instead (no API key/billing needed). If your rubric requires
> Google Maps specifically, check with your instructor — swapping back is a contained change
> (`flutter_map` → `google_maps_flutter` in `lib/widgets/map_picker.dart` and
> `lib/screens/*/route_screen.dart`).

Two project folders make up the system:

| Folder | What it is |
|---|---|
| `C:\laragon\www\food-donation` | Laravel 12 REST API backend |
| `C:\laragon\www\food_donation_app` | Flutter mobile app (helper / requester / admin) |

---

## 1. Prerequisites

- [x] Laragon (PHP 8.2+, MySQL, Composer) — already installed on this machine
- [x] Flutter SDK (stable channel) — already installed on this machine
- [x] Node.js — needed for the Firebase/OneSignal-adjacent tooling (already installed)
- Android Studio (for the Android emulator/SDK) or a physical Android device with USB debugging
- [SQLyog](https://webyog.com/product/sqlyog/) (or SQLyog Community) to browse the MySQL database
- A free [Cloudinary](https://cloudinary.com/) account (image upload)
- A free [OneSignal](https://onesignal.com/) account (push notifications)
- Nothing extra for maps/chat — OpenStreetMap tiles are free/keyless, and Reverb (chat) is
  self-hosted (no external account)

---

## 2. Backend: Laravel API

### 2.1 Start MySQL

Open Laragon and click **Start All** (or start MySQL only). The API will not boot without it.

### 2.2 Install dependencies (already done if you're continuing this build)

```bash
cd C:\laragon\www\food-donation
composer install
```

### 2.3 Environment

The `.env` file is already configured for this machine:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=food_donation
DB_USERNAME=root
DB_PASSWORD=
```

The `food_donation` database has already been created. If you need to recreate it:

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS food_donation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 2.4 Migrate + seed

```bash
php artisan migrate:fresh --seed
```

This creates all tables (`users`, `categories`, `help_offers`, `assistance_requests`,
`messages`, `ratings`, plus Sanctum's `personal_access_tokens`) and seeds:

- 8 help categories (Medical Transport, Food Assistance, Volunteer Work, Equipment Loan, …)
- **Admin** account: `admin@communityhelp.test` / `password`
- **Helper** demo account: `helper@communityhelp.test` / `password` (with 3 sample help offers)
- **Requester** demo account: `requester@communityhelp.test` / `password`

### 2.5 Run the API + chat server

Two processes need to run together:

**A. The REST API** — either:
- Laragon virtual host (recommended): visit `http://food-donation.test/api/categories` in a
  browser — you should see JSON, or
- `php artisan serve` — runs on `http://127.0.0.1:8000`; the Android emulator reaches your
  host machine at `10.0.2.2`, already the default in `lib/config/api_config.dart`.

**B. The Reverb WebSocket server** (for real-time chat) — in a **separate terminal**:

```bash
php artisan reverb:start
```

Leave this running for as long as you want chat to work live. If it's not running, every
other feature still works — only chat message sending will show a broadcast error.

### 2.6 SQLyog

Open SQLyog → **New Connection**:

- Host: `127.0.0.1`
- Port: `3306`
- Username: `root`
- Password: *(blank)*
- Database: `food_donation`

You'll see `users`, `categories`, `help_offers`, `assistance_requests`, `messages`,
`ratings`, and `personal_access_tokens`.

### 2.7 Quick API smoke test

```bash
curl http://127.0.0.1:8000/api/categories
curl -X POST http://127.0.0.1:8000/api/login -H "Content-Type: application/json" \
  -d "{\"email\":\"helper@communityhelp.test\",\"password\":\"password\"}"
```

---

## 3. Cloud service setup

### 3.1 Cloudinary (image upload)

1. Sign up at [cloudinary.com](https://cloudinary.com/) and open the Dashboard — copy your
   **Cloud Name**.
2. Go to **Settings → Upload → Upload presets → Add upload preset**. Set **Signing Mode**
   to **Unsigned**, save, and copy the preset name.
3. Update `food_donation_app/lib/config/api_config.dart`:
   ```dart
   static const String cloudinaryCloudName = 'your-cloud-name';
   static const String cloudinaryUploadPreset = 'your-preset-name';
   ```
   (Already filled in with a working demo account's values for this machine.)

The Flutter app uploads images **directly** to Cloudinary (`CloudinaryService`) and sends
only the returned `secure_url` to the Laravel API — the backend never touches the binary
image data.

### 3.2 OneSignal (push notifications)

1. Sign up at [onesignal.com](https://onesignal.com/) → **New App/Website** → choose
   **Google Android (FCM)** for the platform (Firebase Cloud Messaging is required
   underneath — OneSignal's setup wizard walks you through creating a free Firebase project
   just for this, separate from any app-level Firebase usage).
2. Once created, go to **Settings → Keys & IDs** and copy the **OneSignal App ID** and
   **REST API Key**.
3. Add to the Laravel `.env`:
   ```
   ONESIGNAL_APP_ID=your-app-id
   ONESIGNAL_REST_API_KEY=your-rest-api-key
   ```
4. Update `food_donation_app/lib/config/api_config.dart`:
   ```dart
   static const String oneSignalAppId = 'your-app-id';
   ```
5. Follow OneSignal's [Flutter SDK setup](https://documentation.onesignal.com/docs/flutter-sdk-setup)
   for the native Android configuration (adding `google-services.json` to
   `android/app/`, applying the Google Services Gradle plugin) — required for push to
   actually arrive on Android.

Without this configured, the app still works fully — `NotificationService` fails silently
(logs a debug message) rather than crashing, same pattern as the earlier Firebase/Google Maps
placeholders.

**What triggers a push:** new request on your help offer, request approved/rejected, marked
completed, and new chat messages (see `app/Services/OneSignalService.php` and where it's
called in `AssistanceRequestController`/`ChatController`).

### 3.3 Maps + routing (OpenStreetMap — no setup needed)

The app uses [`flutter_map`](https://pub.dev/packages/flutter_map) with OpenStreetMap raster
tiles (`lib/widgets/map_picker.dart`) instead of Google Maps — no API key, no billing
account, works out of the box on Android, iOS, and web.

The `RichAttributionWidget` shown on the map ("OpenStreetMap contributors") is required by
[OSM's tile usage policy](https://operations.osmfoundation.org/policies/tiles/) — don't remove
it.

**Address search** uses OSM's free [Nominatim](https://nominatim.org/) geocoding API
(`LocationService`) — also keyless. Its usage policy caps free usage at roughly
1 request/second — fine for interactive use, not for bulk/automated lookups.

**Routing** (`lib/services/routing_service.dart`) uses the free public
[OSRM](https://project-osrm.org/) API. It only exposes a generic "driving" profile — Car,
Motorcycle, and Lorry therefore all follow the same road route, and only the *estimated time*
differs (see `VehicleMode.speedMultiplier`), since true multi-vehicle-class routing (e.g.
avoiding low bridges for lorries) needs a paid or self-hosted routing engine.

### 3.4 Chat (Laravel Reverb — self-hosted, no account needed)

Already installed and configured (`laravel/reverb`, `.env` `REVERB_*` keys, `BROADCAST_CONNECTION=reverb`).
The Flutter side speaks the Pusher wire protocol directly over a raw WebSocket
(`lib/services/chat_socket_service.dart`) rather than using `pusher_channels_flutter` — that
package only supports Pusher.com's cloud clusters, not a custom host/port for a self-hosted
server like Reverb.

Remember: `php artisan reverb:start` must be running (section 2.5B) for messages to
broadcast live. If you regenerate keys with `php artisan reverb:install`, update the matching
constants in `food_donation_app/lib/config/api_config.dart` (`reverbAppKey`).

---

## 4. Flutter app

### 4.1 Install dependencies

```bash
cd C:\laragon\www\food_donation_app
flutter pub get
```

### 4.2 Point the app at your backend

`lib/config/api_config.dart` already auto-detects Flutter Web vs. the Android emulator, for
both the REST API and the Reverb WebSocket:

```dart
static String get baseUrl {
  if (kIsWeb) return 'http://127.0.0.1:8000/api';   // Chrome/Edge — shares the host network
  return 'http://10.0.2.2:8000/api';                 // Android emulator — special host alias
}
```

For a **physical device**, neither applies — hardcode your machine's LAN IP instead, e.g.
`http://192.168.1.10:8000/api` (and the matching `reverbHost`), and make sure the phone is on
the same Wi-Fi network as this PC (Windows Firewall may also need to allow inbound
connections on ports 8000 and 8080).

### 4.3 Android permissions

Already configured in `android/app/src/main/AndroidManifest.xml` — `INTERNET`,
`ACCESS_FINE_LOCATION` and `ACCESS_COARSE_LOCATION` are declared. No maps-related
configuration is needed (see section 3.3).

### 4.4 Run

Start an Android emulator (or connect a device with USB debugging), make sure both
`php artisan serve` and `php artisan reverb:start` are running, then:

```bash
flutter run
```

### 4.5 Test accounts

| Role | Email | Password |
|---|---|---|
| Admin | admin@communityhelp.test | password |
| Helper | helper@communityhelp.test | password |
| Requester | requester@communityhelp.test | password |

---

## 5. Project structure reference

```
food-donation/                      Laravel 12 REST API
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/        AuthController, HelpOfferController,
│   │   │                           AssistanceRequestController, CategoryController,
│   │   │                           ChatController, RatingController,
│   │   │                           Admin/{Dashboard,User,HelpOffer,AssistanceRequest}Controller
│   │   ├── Middleware/             EnsureUserHasRole (role:admin|helper|requester)
│   │   ├── Requests/               Form Request validation, grouped by domain
│   │   └── Resources/              UserResource, HelpOfferResource, AssistanceRequestResource,
│   │                                CategoryResource, MessageResource, RatingResource
│   ├── Models/                     User, Category, HelpOffer, AssistanceRequest, Message, Rating
│   ├── Events/                     MessageSent (broadcasts instantly over Reverb)
│   └── Services/                   OneSignalService (push notifications)
├── database/
│   ├── migrations/
│   └── seeders/                    CategorySeeder, AdminUserSeeder, DemoDataSeeder
└── routes/
    ├── api.php                     All REST endpoints
    └── channels.php                Private chat channel authorization

food_donation_app/                  Flutter mobile app
└── lib/
    ├── config/                     api_config.dart, theme.dart
    ├── models/                     UserModel, HelpOfferModel, AssistanceRequestModel,
    │                                CategoryModel, MessageModel, RatingModel, VehicleMode
    ├── services/                   ApiClient, AuthService, HelpOfferService,
    │                                AssistanceRequestService, CategoryService, AdminService,
    │                                ChatService, ChatSocketService (raw Pusher-protocol
    │                                WebSocket client), CloudinaryService, LocationService,
    │                                RoutingService, NotificationService (OneSignal), StorageService
    ├── providers/                  AuthProvider, HelpOfferProvider, AssistanceRequestProvider,
    │                                CategoryProvider, AdminProvider, ChatProvider,
    │                                ChatThreadsProvider (Provider state mgmt)
    ├── screens/
    │   ├── auth/                   LoginScreen, RegisterScreen
    │   ├── shared/                 SplashScreen, HomeScreen, ProfileScreen, EditProfileScreen,
    │   │                            ChatScreen, ChatListScreen
    │   ├── helper/                 MyHelpOffersScreen, AddHelpOfferScreen, EditHelpOfferScreen,
    │   │                            HelpOfferStatusScreen
    │   ├── requester/               HelpOfferListScreen, HelpOfferDetailsScreen,
    │   │                            RequestHistoryScreen, RouteScreen
    │   └── admin/                  AdminDashboardScreen, ManageUsersScreen,
    │                                ManageHelpOffersScreen, ManageRequestsScreen
    └── widgets/                    HelpOfferCard, CustomButton, CustomTextField, StatusBadge,
                                     MapPicker, StatCard, RatingDialog, LoadingWidget/EmptyStateWidget
```

---

## 6. API endpoint reference

| Method | Endpoint | Role | Description |
|---|---|---|---|
| POST | `/api/register` | public | Register helper/requester, returns Sanctum token |
| POST | `/api/login` | public | Email/password login |
| POST | `/api/logout` | any auth | Revoke current token |
| GET/PUT | `/api/profile` | any auth | View / update profile |
| POST | `/api/onesignal/player-id` | any auth | Register device for push notifications |
| GET | `/api/categories` | public | List categories |
| GET | `/api/help-offers` | public | Browse (search, category_id, status filters) |
| GET | `/api/help-offers/{id}` | public | Help offer details |
| GET | `/api/my-help-offers` | helper | Helper's own help offers |
| POST | `/api/help-offers` | helper | Create help offer |
| PUT | `/api/help-offers/{id}` | helper | Update own help offer |
| DELETE | `/api/help-offers/{id}` | helper | Delete own help offer |
| POST | `/api/help-offers/{id}/request` | requester | Request a help offer |
| GET | `/api/my-requests` | requester | Request history |
| PATCH | `/api/requests/{id}/cancel` | requester | Cancel own pending request |
| PATCH | `/api/requests/{id}/approve` | helper | Approve a request on own offer |
| PATCH | `/api/requests/{id}/reject` | helper | Reject a request |
| PATCH | `/api/requests/{id}/complete` | helper | Mark request as completed |
| POST | `/api/requests/{id}/rating` | any auth | Rate the other party (after completed) |
| GET | `/api/my-chats` | any auth | Chat thread list (all conversations) |
| GET/POST | `/api/help-offers/{id}/chat/{user}/messages` | any auth | Chat history / send message |
| GET | `/api/admin/dashboard` | admin | Aggregate stats |
| GET/DELETE/PATCH | `/api/admin/users...` | admin | Manage users |
| GET/DELETE | `/api/admin/help-offers...` | admin | Moderate help offers |
| GET | `/api/admin/requests` | admin | View all requests |
| POST/DELETE | `/api/admin/categories...` | admin | Manage categories |

---

## 7. Deploying to Render

Render has no managed MySQL, so the database lives on a separate free host and Render only
runs the app. Two Render web services are used: the API itself, and a second one dedicated to
Reverb (chat), since a single free web service can't run both the HTTP server and a persistent
WebSocket process together.

### 7.1 MySQL — Clever Cloud (free, works with SQLyog)

1. Sign up at clever-cloud.com → **Create** → **Add-on** → **MySQL** → free "Dev" plan.
2. Open the add-on's **Overview** tab and copy the Host, Port, Database, User, Password.
3. Add those as a new connection in SQLyog — this is now the shared database for everyone on
   the team, replacing the local MySQL instance.

### 7.2 Push to GitHub

`render.yaml` in the repo root already describes both services (`food-donation-api` and
`food-donation-reverb`) as a Render Blueprint, so Render can create them together in one step.

```bash
git remote add origin <your-repo-url>
git push -u origin master
```

### 7.3 Create the services on Render

1. New account at render.com → **New +** → **Blueprint** → connect the repo → Render reads
   `render.yaml` and proposes both services.
2. Approve, then fill in the env vars marked "from render.yaml, needs a value" in the
   dashboard for `food-donation-api`: `APP_KEY`, `APP_URL`, `DB_HOST`, `DB_PORT`,
   `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (from Clever Cloud), `CLOUDINARY_CLOUD_NAME`,
   `CLOUDINARY_UPLOAD_PRESET`, `ONESIGNAL_APP_ID`, `ONESIGNAL_REST_API_KEY`,
   `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` (any random strings — just need to
   match between the two services, which `render.yaml`'s `fromService` links already handle
   automatically).
3. `food-donation-reverb` needs no manual values — it inherits `APP_KEY` and the `REVERB_*`
   credentials straight from `food-donation-api` via the Blueprint's `fromService` references,
   and `REVERB_HOST` on the API side is likewise auto-filled with the Reverb service's Render
   hostname.
4. Deploy. The API service runs `scripts/00-laravel-deploy.sh` on every deploy (migrate +
   cache); the Reverb service just starts `php artisan reverb:start` bound to Render's
   assigned `$PORT`.
5. Point the Flutter app's `api_config.dart` base URL and the Reverb client host at the two
   `*.onrender.com` addresses Render assigns (visible on each service's dashboard page).

---

## 8. Troubleshooting

- **`Connection refused` from the emulator**: use `10.0.2.2`, not `127.0.0.1`/`localhost`,
  as the backend host in `api_config.dart` when running `artisan serve`.
- **Chat messages fail to send**: confirm `php artisan reverb:start` is running in its own
  terminal — check for `Pusher error: cURL error 7... Failed to connect` in the response,
  which means Reverb isn't up.
- **403 responses**: check the account's `role` — routes are strictly separated by
  helper/requester/admin via the `role:` middleware.
- **Image upload fails**: confirm the Cloudinary upload preset's signing mode is
  **Unsigned** — signed uploads require a backend-generated signature, which this
  architecture intentionally avoids.
- **No push notifications**: expected until you complete section 3.2 (OneSignal) — check
  Laravel's log (`storage/logs/laravel.log`) for "OneSignal not configured" messages, which
  confirm the rest of the app is working and this is just the missing optional step.
- **Wondering why folder/package names still say "food donation"**: see the history note at
  the top of this file — purely cosmetic/internal, doesn't affect functionality. To do a full
  clean rename: rename the Laragon folder (`food-donation` → e.g. `community-help`, update
  Laragon's virtual host + `.env` `APP_URL`), and for Flutter run a package-rename tool (e.g.
  the `rename` pub package) rather than hand-editing — it also touches Android
  `applicationId` / iOS bundle ID.
