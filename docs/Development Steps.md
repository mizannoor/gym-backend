# Development Steps

1. **Environment Setup & Project Scaffolding**

   1. Install Xcode (latest stable) and create a new SwiftUI project named **GymMembershipApp**.
   2. Install Homebrew (if needed), then install Composer globally.
   3. Use Composer to install the Laravel installer:

      ```bash
      composer global require laravel/installer
      ```
   4. Scaffold a new Laravel project for your backend:

      ```bash
      laravel new gym-backend
      ```
   5. In both projects, configure environment variables:

      * **iOS `.xcconfig`** (or Info.plist) for Google OAuth client ID
      * **Laravel `.env`** for `DB_…`, mail settings, Square credentials, and Google client ID/secret

2. **Database Design & Laravel API Foundation**

   1. Draw an ER diagram covering these tables:

      * `users`
      * `membership_plans`
      * `memberships`
      * `payments`
   2. Create Laravel migrations for each table (`php artisan make:migration …`).
   3. Define Eloquent models with relationships:

      * `User` ↔ `Membership` (one-to-one)
      * `MembershipPlan` ↔ `Membership` (one-to-many)
      * `User` ↔ `Payment` (one-to-many)
   4. Write seeders for default plans (`php artisan make:seeder PlanSeeder`).
   5. Generate API resource controllers:

      ```bash
      php artisan make:controller API/UserController --api
      php artisan make:controller API/PlanController --api
      ```
   6. Define RESTful routes in `routes/api.php`.

3. **Authentication Module (Gmail Only)**

   1. Require Socialite:

      ```bash
      composer require laravel/socialite
      ```
   2. Add Google credentials to `config/services.php`.
   3. Install JWT support:

      ```bash
      composer require tymon/jwt-auth
      php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
      ```
   4. Create `AuthController` with:

      * `redirectToGoogle()`
      * `handleGoogleCallback()` (register/login + JWT issue)
   5. Protect routes with `auth:api` middleware.
   6. In Xcode, integrate Google Sign-In SDK via Swift Package Manager.
   7. Build SwiftUI views:

      1. **SignUpView** – trigger Google Sign-In flow.
      2. **SignInView** – handle callback, call `/api/auth/google`, store JWT in Keychain.

4. **Dashboard & QR Code Generation**

   1. Require a QR-code library in Laravel:

      ```bash
      composer require bacon/bacon-qr-code
      ```
   2. Add `/api/dashboard` endpoint returning:

      * Membership status JSON
      * Base64-encoded QR code string
   3. In SwiftUI:

      1. Create `DashboardView` that fetches `/api/dashboard`.
      2. Decode JSON into `DashboardData` model.
      3. Convert Base64 string to `Image(uiImage:)` and display.
      4. Wrap in `NavigationView` with dark-mode styling.

5. **Membership Plan Subscription**

   1. In Laravel:

      1. `PlanController@index()` lists plans.
      2. `SubscriptionController@subscribe(Request $r)` attaches a plan to the user and sets `expires_at`.
   2. Define routes:

      ```php
      Route::get('plans', 'PlanController@index');
      Route::post('subscribe', 'SubscriptionController@subscribe');
      ```
   3. In SwiftUI:

      1. Create `PlanSelectionView`—fetch and display plans in a `List` or `ScrollView`.
      2. On tap, call `/api/subscribe` with JSON `{ plan_id: … }`.
      3. Show in-app confirmation on success.

6. **Payment Processing with SquareUp API**

   1. Install Square SDK in Laravel:

      ```bash
      composer require square/square
      ```
   2. Add Square credentials to `.env`.
   3. In `PaymentController`:

      1. `createPayment(Request $r)` → builds and returns a payment token.
      2. `webhookHandler(Request $r)` → listens for Square webhooks to update payment status.
   4. Register webhook route in `routes/api.php`.
   5. In SwiftUI:

      1. Add Square iOS SDK via CocoaPods/SPM.
      2. Create `PaymentView` to collect card details.
      3. Call `/api/payment/create`, then complete transaction with Square’s SDK.
      4. Handle callbacks and display success/failure.

7. **Email Notification on Renewal**

   1. Configure mail driver in `config/mail.php` and `.env`.
   2. Create `MembershipRenewalNotification` mailable with Markdown or Blade template.
   3. After payment success, dispatch:

      ```php
      Mail::to($user->email)->queue(
        new MembershipRenewalNotification($membership)
      );
      ```
   4. Ensure `QUEUE_DRIVER` is set (e.g., `database`), run `php artisan queue:work`.

8. **Search Functionality**

   1. In Laravel controllers, add query-scope or `where('name','like',"%{$term}%")`.
   2. Expose endpoints:

      ```php
      Route::get('search/users', 'UserController@search');
      Route::get('search/plans', 'PlanController@search');
      ```
   3. In SwiftUI:

      1. Build a `SearchBar` (UIKit wrapper) or custom view.
      2. Bind text to a `@Published` property and debounce using Combine.
      3. Call search endpoints and display results in `List`.

9. **API Consumption & Error Handling in SwiftUI**

   1. Create an `APIClient` class wrapping `URLSession` (or Alamofire).
   2. Attach JWT token in `Authorization: Bearer …` header for each request.
   3. Handle HTTP status codes:

      * **401** → prompt re-login or token refresh
      * **422** → parse validation errors and show inline messages
      * **500** → show generic error alert
   4. Centralize error alerts using a `@Published var currentError: APIError?`

10. **Testing, Emulation & Demo Prep**

    1. **Laravel:** write PHPUnit tests for:

       * Authentication flows
       * CRUD endpoints
       * Payment webhooks
    2. **SwiftUI:** write XCTest tests for:

       * Model decoding
       * ViewModels
       * UI interactions (sign-in, plan selection)
    3. Run the iOS app on Simulator (iPhone 14), walk through:

       * Sign up/in
       * Dashboard display
       * Plan subscription & payment
    4. Capture screenshots and console logs for your report.

11. **Scalability & Security Review**

    1. **Laravel hardening:**

       * Enable rate limiting (`throttle:60,1`)
       * Use FormRequest validation for all inputs
       * Enforce HTTPS via middleware and `APP_URL`
       * Add database indexes on foreign keys and timestamp columns
    2. **SwiftUI optimization:**

       * Use `@StateObject` instead of `@ObservedObject` where appropriate
       * Lazy-load images and lists
       * Profile memory and CPU in Instruments
    3. Audit dependencies for vulnerabilities (`composer audit`, `swift audit`).
