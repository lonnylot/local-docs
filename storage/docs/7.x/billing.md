# Laravel Cashier

- [Introduction](#introduction)
- [Upgrading Cashier](#upgrading-cashier)
- [Installation](#installation)
- [Configuration](#configuration)
    - [Billable Model](#billable-model)
    - [API Keys](#api-keys)
    - [Currency Configuration](#currency-configuration)
    - [Logging](#logging)
- [Customers](#customers)
    - [Retrieving Customers](#retrieving-customers)
    - [Creating Customers](#creating-customers)
    - [Updating Customers](#updating-customers)
    - [Billing Portal](#billing-portal)
- [Payment Methods](#payment-methods)
    - [Storing Payment Methods](#storing-payment-methods)
    - [Retrieving Payment Methods](#retrieving-payment-methods)
    - [Determining If A User Has A Payment Method](#check-for-a-payment-method)
    - [Updating The Default Payment Method](#updating-the-default-payment-method)
    - [Adding Payment Methods](#adding-payment-methods)
    - [Deleting Payment Methods](#deleting-payment-methods)
- [Subscriptions](#subscriptions)
    - [Creating Subscriptions](#creating-subscriptions)
    - [Checking Subscription Status](#checking-subscription-status)
    - [Changing Plans](#changing-plans)
    - [Subscription Quantity](#subscription-quantity)
    - [Multiplan Subscriptions](#multiplan-subscriptions)
    - [Subscription Taxes](#subscription-taxes)
    - [Subscription Anchor Date](#subscription-anchor-date)
    - [Cancelling Subscriptions](#cancelling-subscriptions)
    - [Resuming Subscriptions](#resuming-subscriptions)
- [Subscription Trials](#subscription-trials)
    - [With Payment Method Up Front](#with-payment-method-up-front)
    - [Without Payment Method Up Front](#without-payment-method-up-front)
    - [Extending Trials](#extending-trials)
- [Handling Stripe Webhooks](#handling-stripe-webhooks)
    - [Defining Webhook Event Handlers](#defining-webhook-event-handlers)
    - [Failed Subscriptions](#handling-failed-subscriptions)
    - [Verifying Webhook Signatures](#verifying-webhook-signatures)
- [Single Charges](#single-charges)
    - [Simple Charge](#simple-charge)
    - [Charge With Invoice](#charge-with-invoice)
    - [Refunding Charges](#refunding-charges)
- [Invoices](#invoices)
    - [Retrieving Invoices](#retrieving-invoices)
    - [Generating Invoice PDFs](#generating-invoice-pdfs)
- [Handling Failed Payments](#handling-failed-payments)
- [Strong Customer Authentication (SCA)](#strong-customer-authentication)
    - [Payments Requiring Additional Confirmation](#payments-requiring-additional-confirmation)
    - [Off-session Payment Notifications](#off-session-payment-notifications)
- [Stripe SDK](#stripe-sdk)
- [Testing](#testing)

<a name="introduction"></a>
## Introduction

Laravel Cashier provides an expressive, fluent interface to [Stripe's](https://stripe.com) subscription billing services. It handles almost all of the boilerplate subscription billing code you are dreading writing. In addition to basic subscription management, Cashier can handle coupons, swapping subscription, subscription "quantities", cancellation grace periods, and even generate invoice PDFs.

<a name="upgrading-cashier"></a>
## Upgrading Cashier

When upgrading to a new version of Cashier, it's important that you carefully review [the upgrade guide](https://github.com/laravel/cashier-stripe/blob/master/UPGRADE.md).

> {note} To prevent breaking changes, Cashier uses a fixed Stripe API version. Cashier 12 utilizes Stripe API version `2020-03-02`. The Stripe API version will be updated on minor releases in order to make use of new Stripe features and improvements.

<a name="installation"></a>
## Installation

First, require the Cashier package for Stripe with Composer:

    composer require laravel/cashier

> {note} To ensure Cashier properly handles all Stripe events, remember to [set up Cashier's webhook handling](#handling-stripe-webhooks).

#### Database Migrations

The Cashier service provider registers its own database migration directory, so remember to migrate your database after installing the package. The Cashier migrations will add several columns to your `users` table as well as create a new `subscriptions` table to hold all of your customer's subscriptions:

    php artisan migrate

If you need to overwrite the migrations that ship with the Cashier package, you can publish them using the `vendor:publish` Artisan command:

    php artisan vendor:publish --tag="cashier-migrations"

If you would like to prevent Cashier's migrations from running entirely, you may use the `ignoreMigrations` provided by Cashier. Typically, this method should be called in the `register` method of your `AppServiceProvider`:

    use Laravel\Cashier\Cashier;

    Cashier::ignoreMigrations();

> {note} Stripe recommends that any column used for storing Stripe identifiers should be case-sensitive. Therefore, you should ensure the column collation for the `stripe_id` column is set to, for example, `utf8_bin` in MySQL. More info can be found [in the Stripe documentation](https://stripe.com/docs/upgrades#what-changes-does-stripe-consider-to-be-backwards-compatible).

<a name="configuration"></a>
## Configuration

<a name="billable-model"></a>
### Billable Model

Before using Cashier, add the `Billable` trait to your model definition. This trait provides various methods to allow you to perform common billing tasks, such as creating subscriptions, applying coupons, and updating payment method information:

    use Laravel\Cashier\Billable;

    class User extends Authenticatable
    {
        use Billable;
    }

Cashier assumes your Billable model will be the `App\User` class that ships with Laravel. If you wish to change this you can specify a different model in your `.env` file:

    CASHIER_MODEL=App\User

> {note} If you're using a model other than Laravel's supplied `App\User` model, you'll need to publish and alter the [migrations](#installation) provided to match your alternative model's table name.

<a name="api-keys"></a>
### API Keys

Next, you should configure your Stripe keys in your `.env` file. You can retrieve your Stripe API keys from the Stripe control panel.

    STRIPE_KEY=your-stripe-key
    STRIPE_SECRET=your-stripe-secret

<a name="currency-configuration"></a>
### Currency Configuration

The default Cashier currency is United States Dollars (USD). You can change the default currency by setting the `CASHIER_CURRENCY` environment variable:

    CASHIER_CURRENCY=eur

In addition to configuring Cashier's currency, you may also specify a locale to be used when formatting money values for display on invoices. Internally, Cashier utilizes [PHP's `NumberFormatter` class](https://www.php.net/manual/en/class.numberformatter.php) to set the currency locale:

    CASHIER_CURRENCY_LOCALE=nl_BE

> {note} In order to use locales other than `en`, ensure the `ext-intl` PHP extension is installed and configured on your server.

<a name="logging"></a>
#### Logging

Cashier allows you to specify the log channel to be used when logging all Stripe related exceptions. You may specify the log channel using the `CASHIER_LOGGER` environment variable:

    CASHIER_LOGGER=stack

<a name="customers"></a>
## Customers

<a name="retrieving-customers"></a>
### Retrieving Customers

You can retrieve a customer by their Stripe ID using the `Cashier::findBillable` method. This will return an instance of the Billable model:

    use Laravel\Cashier\Cashier;

    $user = Cashier::findBillable($stripeId);

<a name="creating-customers"></a>
### Creating Customers

Occasionally, you may wish to create a Stripe customer without beginning a subscription. You may accomplish this using the `createAsStripeCustomer` method:

    $stripeCustomer = $user->createAsStripeCustomer();

Once the customer has been created in Stripe, you may begin a subscription at a later date. You can also use an optional `$options` array to pass in any additional parameters which are supported by the Stripe API:

    $stripeCustomer = $user->createAsStripeCustomer($options);

You may use the `asStripeCustomer` method if you want to return the customer object if the billable entity is already a customer within Stripe:

    $stripeCustomer = $user->asStripeCustomer();

The `createOrGetStripeCustomer` method may be used if you want to return the customer object but are not sure whether the billable entity is already a customer within Stripe. This method will create a new customer in Stripe if one does not already exist:

    $stripeCustomer = $user->createOrGetStripeCustomer();

<a name="updating-customers"></a>
### Updating Customers

Occasionally, you may wish to update the Stripe customer directly with additional information. You may accomplish this using the `updateStripeCustomer` method:

    $stripeCustomer = $user->updateStripeCustomer($options);

<a name="billing-portal"></a>
### Billing Portal

Stripe offers [an easy way to set up a billing portal](https://stripe.com/docs/billing/subscriptions/customer-portal) so your customer can manage their subscription, payment methods, and view their billing history. You can redirect your users to the billing portal using the `redirectToBillingPortal` method from a controller or route:

    use Illuminate\Http\Request;

    public function billingPortal(Request $request)
    {
        return $request->user()->redirectToBillingPortal();
    }

By default, when the user is finished managing their subscription, they can return to the `home` route of your application. You may provide a custom URL the user should return to by passing the URL as an argument to the `redirectToBillingPortal` method:

    use Illuminate\Http\Request;

    public function billingPortal(Request $request)
    {
        return $request->user()->redirectToBillingPortal(
            route('billing')
        );
    }

If you would like to only generate the URL to the billing portal, you may use the `billingPortalUrl` method:

    $url = $user->billingPortalUrl(route('billing'));

<a name="payment-methods"></a>
## Payment Methods

<a name="storing-payment-methods"></a>
### Storing Payment Methods

In order to create subscriptions or perform "one off" charges with Stripe, you will need to store a payment method and retrieve its identifier from Stripe. The approach used to accomplish differs based on whether you plan to use the payment method for subscriptions or single charges, so we will examine both below.

#### Payment Methods For Subscriptions

When storing credit cards to a customer for future use, the Stripe Setup Intents API must be used to securely gather the customer's payment method details. A "Setup Intent" indicates to Stripe the intention to charge a customer's payment method. Cashier's `Billable` trait includes the `createSetupIntent` to easily create a new Setup Intent. You should call this method from the route or controller that will render the form which gathers your customer's payment method details:

    return view('update-payment-method', [
        'intent' => $user->createSetupIntent()
    ]);

After you have created the Setup Intent and passed it to the view, you should attach its secret to the element that will gather the payment method. For example, consider this "update payment method" form:

    <input id="card-holder-name" type="text">

    <!-- Stripe Elements Placeholder -->
    <div id="card-element"></div>

    <button id="card-button" data-secret="{{ $intent->client_secret }}">
        Update Payment Method
    </button>

Next, the Stripe.js library may be used to attach a Stripe Element to the form and securely gather the customer's payment details:

    <script src="https://js.stripe.com/v3/"></script>

    <script>
        const stripe = Stripe('stripe-public-key');

        const elements = stripe.elements();
        const cardElement = elements.create('card');

        cardElement.mount('#card-element');
    </script>

Next, the card can be verified and a secure "payment method identifier" can be retrieved from Stripe using [Stripe's `confirmCardSetup` method](https://stripe.com/docs/js/setup_intents/confirm_card_setup):

    const cardHolderName = document.getElementById('card-holder-name');
    const cardButton = document.getElementById('card-button');
    const clientSecret = cardButton.dataset.secret;

    cardButton.addEventListener('click', async (e) => {
        const { setupIntent, error } = await stripe.confirmCardSetup(
            clientSecret, {
                payment_method: {
                    card: cardElement,
                    billing_details: { name: cardHolderName.value }
                }
            }
        );

        if (error) {
            // Display "error.message" to the user...
        } else {
            // The card has been verified successfully...
        }
    });

After the card has been verified by Stripe, you may pass the resulting `setupIntent.payment_method` identifier to your Laravel application, where it can be attached to the customer. The payment method can either be [added as a new payment method](#adding-payment-methods) or [used to update the default payment method](#updating-the-default-payment-method). You can also immediately use the payment method identifier to [create a new subscription](#creating-subscriptions).

> {tip} If you would like more information about Setup Intents and gathering customer payment details please [review this overview provided by Stripe](https://stripe.com/docs/payments/save-and-reuse#php).

#### Payment Methods For Single Charges

Of course, when making a single charge against a customer's payment method we'll only need to use a payment method identifier a single time. Due to Stripe limitations, you may not use the stored default payment method of a customer for single charges. You must allow the customer to enter their payment method details using the Stripe.js library. For example, consider the following form:

    <input id="card-holder-name" type="text">

    <!-- Stripe Elements Placeholder -->
    <div id="card-element"></div>

    <button id="card-button">
        Process Payment
    </button>

Next, the Stripe.js library may be used to attach a Stripe Element to the form and securely gather the customer's payment details:

    <script src="https://js.stripe.com/v3/"></script>

    <script>
        const stripe = Stripe('stripe-public-key');

        const elements = stripe.elements();
        const cardElement = elements.create('card');

        cardElement.mount('#card-element');
    </script>

Next, the card can be verified and a secure "payment method identifier" can be retrieved from Stripe using [Stripe's `createPaymentMethod` method](https://stripe.com/docs/stripe-js/reference#stripe-create-payment-method):

    const cardHolderName = document.getElementById('card-holder-name');
    const cardButton = document.getElementById('card-button');

    cardButton.addEventListener('click', async (e) => {
        const { paymentMethod, error } = await stripe.createPaymentMethod(
            'card', cardElement, {
                billing_details: { name: cardHolderName.value }
            }
        );

        if (error) {
            // Display "error.message" to the user...
        } else {
            // The card has been verified successfully...
        }
    });

If the card is verified successfully, you may pass the `paymentMethod.id` to your Laravel application and process a [single charge](#simple-charge).

<a name="retrieving-payment-methods"></a>
### Retrieving Payment Methods

The `paymentMethods` method on the Billable model instance returns a collection of `Laravel\Cashier\PaymentMethod` instances:

    $paymentMethods = $user->paymentMethods();

To retrieve the default payment method, the `defaultPaymentMethod` method may be used:

    $paymentMethod = $user->defaultPaymentMethod();

You can also retrieve a specific payment method that is owned by the Billable model using the `findPaymentMethod` method:

    $paymentMethod = $user->findPaymentMethod($paymentMethodId);

<a name="check-for-a-payment-method"></a>
### Determining If A User Has A Payment Method

To determine if a Billable model has a default payment method attached to their account, use the `hasDefaultPaymentMethod` method:

    if ($user->hasDefaultPaymentMethod()) {
        //
    }

To determine if a Billable model has at least one payment method attached to their account, use the `hasPaymentMethod` method:

    if ($user->hasPaymentMethod()) {
        //
    }

<a name="updating-the-default-payment-method"></a>
### Updating The Default Payment Method

The `updateDefaultPaymentMethod` method may be used to update a customer's default payment method information. This method accepts a Stripe payment method identifier and will assign the new payment method as the default billing payment method:

    $user->updateDefaultPaymentMethod($paymentMethod);

To sync your default payment method information with the customer's default payment method information in Stripe, you may use the `updateDefaultPaymentMethodFromStripe` method:

    $user->updateDefaultPaymentMethodFromStripe();

> {note} The default payment method on a customer can only be used for invoicing and creating new subscriptions. Due to limitations from Stripe, it may not be used for single charges.

<a name="adding-payment-methods"></a>
### Adding Payment Methods

To add a new payment method, you may call the `addPaymentMethod` method on the billable user, passing the payment method identifier:

    $user->addPaymentMethod($paymentMethod);

> {tip} To learn how to retrieve payment method identifiers please review the [payment method storage documentation](#storing-payment-methods).

<a name="deleting-payment-methods"></a>
### Deleting Payment Methods

To delete a payment method, you may call the `delete` method on the `Laravel\Cashier\PaymentMethod` instance you wish to delete:

    $paymentMethod->delete();

The `deletePaymentMethods` method will delete all of the payment method information for the Billable model:

    $user->deletePaymentMethods();

> {note} If a user has an active subscription, you should prevent them from deleting their default payment method.

<a name="subscriptions"></a>
## Subscriptions

<a name="creating-subscriptions"></a>
### Creating Subscriptions

To create a subscription, first retrieve an instance of your billable model, which typically will be an instance of `App\User`. Once you have retrieved the model instance, you may use the `newSubscription` method to create the model's subscription:

    $user = User::find(1);

    $user->newSubscription('default', 'price_premium')->create($paymentMethod);

The first argument passed to the `newSubscription` method should be the name of the subscription. If your application only offers a single subscription, you might call this `default` or `primary`. The second argument is the specific plan the user is subscribing to. This value should correspond to the plan's price identifier in Stripe.

The `create` method, which accepts [a Stripe payment method identifier](#storing-payment-methods) or Stripe `PaymentMethod` object, will begin the subscription as well as update your database with the customer ID and other relevant billing information.

> {note} Passing a payment method identifier directly to the `create()` subscription method will also automatically add it to the user's stored payment methods.

#### Quantities

If you would like to set a specific quantity for the plan when creating the subscription, you may use the `quantity` method:

    $user->newSubscription('default', 'price_monthly')
         ->quantity(5)
         ->create($paymentMethod);

#### Additional Details

If you would like to specify additional customer or subscription details, you may do so by passing them as the second and third arguments to the `create` method:

    $user->newSubscription('default', 'price_monthly')->create($paymentMethod, [
        'email' => $email,
    ], [
        'metadata' => ['note' => 'Some extra information.'],
    ]);

To learn more about the additional fields supported by Stripe, check out Stripe's documentation on [customer creation](https://stripe.com/docs/api#create_customer) and [subscription creation](https://stripe.com/docs/api/subscriptions/create).

#### Coupons

If you would like to apply a coupon when creating the subscription, you may use the `withCoupon` method:

    $user->newSubscription('default', 'price_monthly')
         ->withCoupon('code')
         ->create($paymentMethod);

#### Adding Subscriptions

If you would like to add a subscription to a customer who already has a default payment method set you can use the `add` method when using the `newSubscription` method:

    $user = User::find(1);

    $user->newSubscription('default', 'price_premium')->add();

<a name="checking-subscription-status"></a>
### Checking Subscription Status

Once a user is subscribed to your application, you may easily check their subscription status using a variety of convenient methods. First, the `subscribed` method returns `true` if the user has an active subscription, even if the subscription is currently within its trial period:

    if ($user->subscribed('default')) {
        //
    }

The `subscribed` method also makes a great candidate for a [route middleware](/docs/{{version}}/middleware), allowing you to filter access to routes and controllers based on the user's subscription status:

    public function handle($request, Closure $next)
    {
        if ($request->user() && ! $request->user()->subscribed('default')) {
            // This user is not a paying customer...
            return redirect('billing');
        }

        return $next($request);
    }

If you would like to determine if a user is still within their trial period, you may use the `onTrial` method. This method can be useful for displaying a warning to the user that they are still on their trial period:

    if ($user->subscription('default')->onTrial()) {
        //
    }

The `subscribedToPlan` method may be used to determine if the user is subscribed to a given plan based on a given Stripe Price ID. In this example, we will determine if the user's `default` subscription is actively subscribed to the `monthly` plan:

    if ($user->subscribedToPlan('price_monthly', 'default')) {
        //
    }

By passing an array to the `subscribedToPlan` method, you may determine if the user's `default` subscription is actively subscribed to the `monthly` or the `yearly` plan:

    if ($user->subscribedToPlan(['price_monthly', 'price_yearly'], 'default')) {
        //
    }

The `recurring` method may be used to determine if the user is currently subscribed and is no longer within their trial period:

    if ($user->subscription('default')->recurring()) {
        //
    }

#### Cancelled Subscription Status

To determine if the user was once an active subscriber, but has cancelled their subscription, you may use the `cancelled` method:

    if ($user->subscription('default')->cancelled()) {
        //
    }

You may also determine if a user has cancelled their subscription, but are still on their "grace period" until the subscription fully expires. For example, if a user cancels a subscription on March 5th that was originally scheduled to expire on March 10th, the user is on their "grace period" until March 10th. Note that the `subscribed` method still returns `true` during this time:

    if ($user->subscription('default')->onGracePeriod()) {
        //
    }

To determine if the user has cancelled their subscription and is no longer within their "grace period", you may use the `ended` method:

    if ($user->subscription('default')->ended()) {
        //
    }

#### Subscription Scopes

Most subscription states are also available as query scopes so that you may easily query your database for subscriptions that are in a given state:

    // Get all active subscriptions...
    $subscriptions = Subscription::query()->active()->get();

    // Get all of the cancelled subscriptions for a user...
    $subscriptions = $user->subscriptions()->cancelled()->get();

A complete list of available scopes is available below:

    Subscription::query()->active();
    Subscription::query()->cancelled();
    Subscription::query()->ended();
    Subscription::query()->incomplete();
    Subscription::query()->notCancelled();
    Subscription::query()->notOnGracePeriod();
    Subscription::query()->notOnTrial();
    Subscription::query()->onGracePeriod();
    Subscription::query()->onTrial();
    Subscription::query()->pastDue();
    Subscription::query()->recurring();

<a name="incomplete-and-past-due-status"></a>
#### Incomplete and Past Due Status

If a subscription requires a secondary payment action after creation the subscription will be marked as `incomplete`. Subscription statuses are stored in the `stripe_status` column of Cashier's `subscriptions` database table.

Similarly, if a secondary payment action is required when swapping plans the subscription will be marked as `past_due`. When your subscription is in either of these states it will not be active until the customer has confirmed their payment. Checking if a subscription has an incomplete payment can be done using the `hasIncompletePayment` method on the Billable model or a subscription instance:

    if ($user->hasIncompletePayment('default')) {
        //
    }

    if ($user->subscription('default')->hasIncompletePayment()) {
        //
    }

When a subscription has an incomplete payment, you should direct the user to Cashier's payment confirmation page, passing the `latestPayment` identifier. You may use the `latestPayment` method available on subscription instance to retrieve this identifier:

    <a href="{{ route('cashier.payment', $subscription->latestPayment()->id) }}">
        Please confirm your payment.
    </a>

If you would like the subscription to still be considered active when it's in a `past_due` state, you may use the `keepPastDueSubscriptionsActive` method provided by Cashier. Typically, this method should be called in the `register` method of your `AppServiceProvider`:

    use Laravel\Cashier\Cashier;

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        Cashier::keepPastDueSubscriptionsActive();
    }

> {note} When a subscription is in an `incomplete` state it cannot be changed until the payment is confirmed. Therefore, the `swap` and `updateQuantity` methods will throw an exception when the subscription is in an `incomplete` state.

<a name="changing-plans"></a>
### Changing Plans

After a user is subscribed to your application, they may occasionally want to change to a new subscription plan. To swap a user to a new subscription, pass the plan's price identifier to the `swap` method:

    $user = App\User::find(1);

    $user->subscription('default')->swap('provider-price-id');

If the user is on trial, the trial period will be maintained. Also, if a "quantity" exists for the subscription, that quantity will also be maintained.

If you would like to swap plans and cancel any trial period the user is currently on, you may use the `skipTrial` method:

    $user->subscription('default')
            ->skipTrial()
            ->swap('provider-price-id');

If you would like to swap plans and immediately invoice the user instead of waiting for their next billing cycle, you may use the `swapAndInvoice` method:

    $user = App\User::find(1);

    $user->subscription('default')->swapAndInvoice('provider-price-id');

#### Prorations

By default, Stripe prorates charges when swapping between plans. The `noProrate` method may be used to update the subscription's without prorating the charges:

    $user->subscription('default')->noProrate()->swap('provider-price-id');

For more information on subscription proration, consult the [Stripe documentation](https://stripe.com/docs/billing/subscriptions/prorations).

> {note} Executing the `noProrate` method before the `swapAndInvoice` method will have no affect on proration. An invoice will always be issued.

<a name="subscription-quantity"></a>
### Subscription Quantity

Sometimes subscriptions are affected by "quantity". For example, your application might charge $10 per month **per user** on an account. To easily increment or decrement your subscription quantity, use the `incrementQuantity` and `decrementQuantity` methods:

    $user = User::find(1);

    $user->subscription('default')->incrementQuantity();

    // Add five to the subscription's current quantity...
    $user->subscription('default')->incrementQuantity(5);

    $user->subscription('default')->decrementQuantity();

    // Subtract five to the subscription's current quantity...
    $user->subscription('default')->decrementQuantity(5);

Alternatively, you may set a specific quantity using the `updateQuantity` method:

    $user->subscription('default')->updateQuantity(10);

The `noProrate` method may be used to update the subscription's quantity without prorating the charges:

    $user->subscription('default')->noProrate()->updateQuantity(10);

For more information on subscription quantities, consult the [Stripe documentation](https://stripe.com/docs/subscriptions/quantities).

> {note} Please note that when working with multiplan subscriptions, an extra "plan" parameter is required for the above quantity methods.

<a name="multiplan-subscriptions"></a>
### Multiplan Subscriptions

[Multiplan subscriptions](https://stripe.com/docs/billing/subscriptions/multiplan) allow you to assign multiple billing plans to a single subscription. For example, imagine you are building a customer service "helpdesk" application that has a base subscription of $10 per month, but offers a live chat add-on plan for an additional $15 per month:

    $user = User::find(1);

    $user->newSubscription('default', [
        'price_monthly',
        'chat-plan',
    ])->create($paymentMethod);

Now the customer will have two plans on their `default` subscription. Both plans will be charged for on their respective billing intervals. You can also use the `quantity` method to indicate the specific quantity for each plan:

    $user = User::find(1);

    $user->newSubscription('default', ['price_monthly', 'chat-plan'])
        ->quantity(5, 'chat-plan')
        ->create($paymentMethod);

Or, you may dynamically add the extra plan and its quantity using the `plan` method:

    $user = User::find(1);

    $user->newSubscription('default', 'price_monthly')
        ->plan('chat-plan', 5)
        ->create($paymentMethod);

Alternatively, you may add a new plan to an existing subscription at a later time:

    $user = User::find(1);

    $user->subscription('default')->addPlan('chat-plan');

The example above will add the new plan and the customer will be billed for it on their next billing cycle. If you would like to bill the customer immediately you may use the `addPlanAndInvoice` method:

    $user->subscription('default')->addPlanAndInvoice('chat-plan');

If you would like to add a plan with a specific quantity, you can pass the quantity as the second parameter of the `addPlan` or `addPlanAndInvoice` methods:

    $user = User::find(1);

    $user->subscription('default')->addPlan('chat-plan', 5);

You may remove plans from subscriptions using the `removePlan` method:

    $user->subscription('default')->removePlan('chat-plan');

> {note} You may not remove the last plan on a subscription. Instead, you should simply cancel the subscription.

### Swapping

You may also change the plans attached to a multiplan subscription. For example, imagine you're on a `basic-plan` subscription with a `chat-plan` add-on and you want to upgrade to the `pro-plan` plan:

    $user = User::find(1);

    $user->subscription('default')->swap(['pro-plan', 'chat-plan']);

When executing the code above, the underlying subscription item with the `basic-plan` is deleted and the one with the `chat-plan` is preserved. Additionally, a new subscription item for the new `pro-plan` is created.

You can also specify subscription item options. For example, you may need to specify the subscription plan quantities:

    $user = User::find(1);

    $user->subscription('default')->swap([
        'pro-plan' => ['quantity' => 5],
        'chat-plan'
    ]);

If you want to swap a single plan on a subscription, you may do so using the `swap` method on the subscription item itself. This approach is useful if you, for example, want to preserve all of the existing metadata on the subscription item.

    $user = User::find(1);

    $user->subscription('default')
            ->findItemOrFail('basic-plan')
            ->swap('pro-plan');

#### Proration

By default, Stripe will prorate charges when adding or removing plans from a subscription. If you would like to make a plan adjustment without proration, you should chain the `noProrate` method onto your plan operation:

    $user->subscription('default')->noProrate()->removePlan('chat-plan');

#### Quantities

If you would like to update quantities on individual subscription plans, you may do so using the [existing quantity methods](#subscription-quantity) and passing the name of the plan as an additional argument to the method:

    $user = User::find(1);

    $user->subscription('default')->incrementQuantity(5, 'chat-plan');

    $user->subscription('default')->decrementQuantity(3, 'chat-plan');

    $user->subscription('default')->updateQuantity(10, 'chat-plan');

> {note} When you have multiple plans set on a subscription the `stripe_plan` and `quantity` attributes on the `Subscription` model will be `null`. To access the individual plans, you should use the `items` relationship available on the `Subscription` model.

#### Subscription Items

When a subscription has multiple plans, it will have multiple subscription "items" stored in your database's `subscription_items` table. You may access these via the `items` relationship on the subscription:

    $user = User::find(1);

    $subscriptionItem = $user->subscription('default')->items->first();

    // Retrieve the Stripe plan and quantity for a specific item...
    $stripePlan = $subscriptionItem->stripe_plan;
    $quantity = $subscriptionItem->quantity;

You can also retrieve a specific plan using the `findItemOrFail` method:

    $user = User::find(1);

    $subscriptionItem = $user->subscription('default')->findItemOrFail('chat-plan');

<a name="subscription-taxes"></a>
### Subscription Taxes

To specify the tax rates a user pays on a subscription, implement the `taxRates` method on your billable model, and return an array with the Tax Rate IDs. You can define these tax rates in [your Stripe dashboard](https://dashboard.stripe.com/test/tax-rates):

    public function taxRates()
    {
        return ['tax-rate-id'];
    }

The `taxRates` method enables you to apply a tax rate on a model-by-model basis, which may be helpful for a user base that spans multiple countries and tax rates. If you're working with multiplan subscriptions you can define different tax rates for each plan by implementing a `planTaxRates` method on your billable model:

    public function planTaxRates()
    {
        return [
            'plan-id' => ['tax-rate-id'],
        ];
    }

> {note} The `taxRates` method only applies to subscription charges. If you use Cashier to make "one off" charges, you will need to manually specify the tax rate at that time.

#### Syncing Tax Rates

When changing the hard-coded Tax Rate IDs returned by the `taxRates` method, the tax settings on any existing subscriptions for the user will remain the same. If you wish to update the tax value for existing subscriptions with the returned `taxTaxRates` values, you should call the `syncTaxRates` method on the user's subscription instance:

    $user->subscription('default')->syncTaxRates();

This will also sync any subscription item tax rates so make sure you also properly change the `planTaxRates` method.

#### Tax Exemption

Cashier also offers methods to determine if the customer is tax exempt by calling the Stripe API. The `isNotTaxExempt`, `isTaxExempt`, and `reverseChargeApplies` methods are available on the billable model:

    $user = User::find(1);

    $user->isTaxExempt();
    $user->isNotTaxExempt();
    $user->reverseChargeApplies();

These methods are also available on any `Laravel\Cashier\Invoice` object. However, when calling these methods on an `Invoice` object the methods will determine the exemption status at the time the invoice was created.

<a name="subscription-anchor-date"></a>
### Subscription Anchor Date

By default, the billing cycle anchor is the date the subscription was created, or if a trial period is used, the date that the trial ends. If you would like to modify the billing anchor date, you may use the `anchorBillingCycleOn` method:

    use App\User;
    use Carbon\Carbon;

    $user = User::find(1);

    $anchor = Carbon::parse('first day of next month');

    $user->newSubscription('default', 'price_premium')
                ->anchorBillingCycleOn($anchor->startOfDay())
                ->create($paymentMethod);

For more information on managing subscription billing cycles, consult the [Stripe billing cycle documentation](https://stripe.com/docs/billing/subscriptions/billing-cycle)

<a name="cancelling-subscriptions"></a>
### Cancelling Subscriptions

To cancel a subscription, call the `cancel` method on the user's subscription:

    $user->subscription('default')->cancel();

When a subscription is cancelled, Cashier will automatically set the `ends_at` column in your database. This column is used to know when the `subscribed` method should begin returning `false`. For example, if a customer cancels a subscription on March 1st, but the subscription was not scheduled to end until March 5th, the `subscribed` method will continue to return `true` until March 5th.

You may determine if a user has cancelled their subscription but are still on their "grace period" using the `onGracePeriod` method:

    if ($user->subscription('default')->onGracePeriod()) {
        //
    }

If you wish to cancel a subscription immediately, call the `cancelNow` method on the user's subscription:

    $user->subscription('default')->cancelNow();

<a name="resuming-subscriptions"></a>
### Resuming Subscriptions

If a user has cancelled their subscription and you wish to resume it, use the `resume` method. The user **must** still be on their grace period in order to resume a subscription:

    $user->subscription('default')->resume();

If the user cancels a subscription and then resumes that subscription before the subscription has fully expired, they will not be billed immediately. Instead, their subscription will be re-activated, and they will be billed on the original billing cycle.

<a name="subscription-trials"></a>
## Subscription Trials

<a name="with-payment-method-up-front"></a>
### With Payment Method Up Front

If you would like to offer trial periods to your customers while still collecting payment method information up front, you should use the `trialDays` method when creating your subscriptions:

    $user = User::find(1);

    $user->newSubscription('default', 'price_monthly')
                ->trialDays(10)
                ->create($paymentMethod);

This method will set the trial period ending date on the subscription record within the database, as well as instruct Stripe to not begin billing the customer until after this date. When using the `trialDays` method, Cashier will overwrite any default trial period configured for the plan in Stripe.

> {note} If the customer's subscription is not cancelled before the trial ending date they will be charged as soon as the trial expires, so you should be sure to notify your users of their trial ending date.

The `trialUntil` method allows you to provide a `DateTime` instance to specify when the trial period should end:

    use Carbon\Carbon;

    $user->newSubscription('default', 'price_monthly')
                ->trialUntil(Carbon::now()->addDays(10))
                ->create($paymentMethod);

You may determine if the user is within their trial period using either the `onTrial` method of the user instance, or the `onTrial` method of the subscription instance. The two examples below are identical:

    if ($user->onTrial('default')) {
        //
    }

    if ($user->subscription('default')->onTrial()) {
        //
    }

#### Defining Trial Days In Stripe / Cashier

You may choose to define how many trial days your plan's receive in the Stripe dashboard or always pass them explicitly using Cashier. If you choose to define your plan's trial days in Stripe you should be aware that new subscriptions, including new subscriptions for a customer that had a subscription in the past, will always receive a trial period unless you explicitly call the `trialDays(0)` method.

<a name="without-payment-method-up-front"></a>
### Without Payment Method Up Front

If you would like to offer trial periods without collecting the user's payment method information up front, you may set the `trial_ends_at` column on the user record to your desired trial ending date. This is typically done during user registration:

    $user = User::create([
        // Populate other user properties...
        'trial_ends_at' => now()->addDays(10),
    ]);

> {note} Be sure to add a [date mutator](/docs/{{version}}/eloquent-mutators#date-mutators) for `trial_ends_at` to your model definition.

Cashier refers to this type of trial as a "generic trial", since it is not attached to any existing subscription. The `onTrial` method on the `User` instance will return `true` if the current date is not past the value of `trial_ends_at`:

    if ($user->onTrial()) {
        // User is within their trial period...
    }

You may also use the `onGenericTrial` method if you wish to know specifically that the user is within their "generic" trial period and has not created an actual subscription yet:

    if ($user->onGenericTrial()) {
        // User is within their "generic" trial period...
    }

Once you are ready to create an actual subscription for the user, you may use the `newSubscription` method as usual:

    $user = User::find(1);

    $user->newSubscription('default', 'price_monthly')->create($paymentMethod);

<a name="extending-trials"></a>
### Extending Trials

The `extendTrial` method allows you to extend the trial period of a subscription after it's been created:

    // End the trial 7 days from now...
    $subscription->extendTrial(
        now()->addDays(7)
    );

    // Add an additional 5 days to the trial...
    $subscription->extendTrial(
        $subscription->trial_ends_at->addDays(5)
    );

If the trial has already expired and the customer is already being billed for the subscription, you can still offer them an extended trial. The time spent within the trial period will be deducted from the customer's next invoice.

<a name="handling-stripe-webhooks"></a>
## Handling Stripe Webhooks

> {tip} You may use [the Stripe CLI](https://stripe.com/docs/stripe-cli) to help test webhooks during local development.

Stripe can notify your application of a variety of events via webhooks. By default, a route that points to Cashier's webhook controller is configured through the Cashier service provider. This controller will handle all incoming webhook requests.

By default, this controller will automatically handle cancelling subscriptions that have too many failed charges (as defined by your Stripe settings), customer updates, customer deletions, subscription updates, and payment method changes; however, as we'll soon discover, you can extend this controller to handle any webhook event you like.

To ensure your application can handle Stripe webhooks, be sure to configure the webhook URL in the Stripe control panel. By default, Cashier's webhook controller listens to the `/stripe/webhook` URL path. The full list of all webhooks you should configure in the Stripe control panel are:

- `customer.subscription.updated`
- `customer.subscription.deleted`
- `customer.updated`
- `customer.deleted`
- `invoice.payment_action_required`

> {note} Make sure you protect incoming requests with Cashier's included [webhook signature verification](/docs/{{version}}/billing#verifying-webhook-signatures) middleware.

#### Webhooks & CSRF Protection

Since Stripe webhooks need to bypass Laravel's [CSRF protection](/docs/{{version}}/csrf), be sure to list the URI as an exception in your `VerifyCsrfToken` middleware or list the route outside of the `web` middleware group:

    protected $except = [
        'stripe/*',
    ];

<a name="defining-webhook-event-handlers"></a>
### Defining Webhook Event Handlers

Cashier automatically handles subscription cancellation on failed charges, but if you have additional webhook events you would like to handle, extend the Webhook controller. Your method names should correspond to Cashier's expected convention, specifically, methods should be prefixed with `handle` and the "camel case" name of the webhook you wish to handle. For example, if you wish to handle the `invoice.payment_succeeded` webhook, you should add a `handleInvoicePaymentSucceeded` method to the controller:

    <?php

    namespace App\Http\Controllers;

    use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;

    class WebhookController extends CashierController
    {
        /**
         * Handle invoice payment succeeded.
         *
         * @param  array  $payload
         * @return \Symfony\Component\HttpFoundation\Response
         */
        public function handleInvoicePaymentSucceeded($payload)
        {
            // Handle The Event
        }
    }

Next, define a route to your Cashier controller within your `routes/web.php` file. This will overwrite the default shipped route:

    Route::post(
        'stripe/webhook',
        '\App\Http\Controllers\WebhookController@handleWebhook'
    );

Cashier emits a `Laravel\Cashier\Events\WebhookReceived` event when a webhook is received, and a `Laravel\Cashier\Events\WebhookHandled` event when a webhook was handled by Cashier. Both events contain the full payload of the Stripe webhook.

<a name="handling-failed-subscriptions"></a>
### Failed Subscriptions

What if a customer's credit card expires? No worries - Cashier's Webhook controller will cancel the customer's subscription for you. Failed payments will automatically be captured and handled by the controller. The controller will cancel the customer's subscription when Stripe determines the subscription has failed (normally after three failed payment attempts).

<a name="verifying-webhook-signatures"></a>
### Verifying Webhook Signatures

To secure your webhooks, you may use [Stripe's webhook signatures](https://stripe.com/docs/webhooks/signatures). For convenience, Cashier automatically includes a middleware which validates that the incoming Stripe webhook request is valid.

To enable webhook verification, ensure that the `STRIPE_WEBHOOK_SECRET` environment variable is set in your `.env` file. The webhook `secret` may be retrieved from your Stripe account dashboard.

<a name="single-charges"></a>
## Single Charges

<a name="simple-charge"></a>
### Simple Charge

> {note} The `charge` method accepts the amount you would like to charge in the **lowest denominator of the currency used by your application**.

If you would like to make a "one off" charge against a subscribed customer's payment method, you may use the `charge` method on a billable model instance. You'll need to [provide a payment method identifier](#storing-payment-methods) as the second argument:

    // Stripe Accepts Charges In Cents...
    $stripeCharge = $user->charge(100, $paymentMethod);

The `charge` method accepts an array as its third argument, allowing you to pass any options you wish to the underlying Stripe charge creation. Consult the Stripe documentation regarding the options available to you when creating charges:

    $user->charge(100, $paymentMethod, [
        'custom_option' => $value,
    ]);

You may also use the `charge` method without an underlying customer or user:

    use App\User;

    $stripeCharge = (new User)->charge(100, $paymentMethod);

The `charge` method will throw an exception if the charge fails. If the charge is successful, an instance of `Laravel\Cashier\Payment` will be returned from the method:

    try {
        $payment = $user->charge(100, $paymentMethod);
    } catch (Exception $e) {
        //
    }

<a name="charge-with-invoice"></a>
### Charge With Invoice

Sometimes you may need to make a one-time charge but also generate an invoice for the charge so that you may offer a PDF receipt to your customer. The `invoiceFor` method lets you do just that. For example, let's invoice the customer $5.00 for a "One Time Fee":

    // Stripe Accepts Charges In Cents...
    $user->invoiceFor('One Time Fee', 500);

The invoice will be charged immediately against the user's default payment method. The `invoiceFor` method also accepts an array as its third argument. This array contains the billing options for the invoice item. The fourth argument accepted by the method is also an array. This final argument accepts the billing options for the invoice itself:

    $user->invoiceFor('Stickers', 500, [
        'quantity' => 50,
    ], [
        'default_tax_rates' => ['tax-rate-id'],
    ]);

> {note} The `invoiceFor` method will create a Stripe invoice which will retry failed billing attempts. If you do not want invoices to retry failed charges, you will need to close them using the Stripe API after the first failed charge.

<a name="refunding-charges"></a>
### Refunding Charges

If you need to refund a Stripe charge, you may use the `refund` method. This method accepts the Stripe Payment Intent ID as its first argument:

    $payment = $user->charge(100, $paymentMethod);

    $user->refund($payment->id);

<a name="invoices"></a>
## Invoices

<a name="retrieving-invoices"></a>
### Retrieving Invoices

You may easily retrieve an array of a billable model's invoices using the `invoices` method:

    $invoices = $user->invoices();

    // Include pending invoices in the results...
    $invoices = $user->invoicesIncludingPending();

You may use the `findInvoice` method to retrieve a specific invoice:

    $invoice = $user->findInvoice($invoiceId);

#### Displaying Invoice Information

When listing the invoices for the customer, you may use the invoice's helper methods to display the relevant invoice information. For example, you may wish to list every invoice in a table, allowing the user to easily download any of them:

    <table>
        @foreach ($invoices as $invoice)
            <tr>
                <td>{{ $invoice->date()->toFormattedDateString() }}</td>
                <td>{{ $invoice->total() }}</td>
                <td><a href="/user/invoice/{{ $invoice->id }}">Download</a></td>
            </tr>
        @endforeach
    </table>

<a name="generating-invoice-pdfs"></a>
### Generating Invoice PDFs

From within a route or controller, use the `downloadInvoice` method to generate a PDF download of the invoice. This method will automatically generate the proper HTTP response to send the download to the browser:

    use Illuminate\Http\Request;

    Route::get('user/invoice/{invoice}', function (Request $request, $invoiceId) {
        return $request->user()->downloadInvoice($invoiceId, [
            'vendor' => 'Your Company',
            'product' => 'Your Product',
        ]);
    });

The `downloadInvoice` method also allows for an optional custom filename as the third parameter. This filename will automatically be suffixed with `.pdf` for you:

    return $request->user()->downloadInvoice($invoiceId, [
        'vendor' => 'Your Company',
        'product' => 'Your Product',
    ], 'my-invoice');

<a name="handling-failed-payments"></a>
## Handling Failed Payments

Sometimes, payments for subscriptions or single charges can fail. When this happens, Cashier will throw an `IncompletePayment` exception that informs you that this happened. After catching this exception, you have two options on how to proceed.

First, you could redirect your customer to the dedicated payment confirmation page which is included with Cashier. This page already has an associated route that is registered via Cashier's service provider. So, you may catch the `IncompletePayment` exception and redirect to the payment confirmation page:

    use Laravel\Cashier\Exceptions\IncompletePayment;

    try {
        $subscription = $user->newSubscription('default', $planId)
                                ->create($paymentMethod);
    } catch (IncompletePayment $exception) {
        return redirect()->route(
            'cashier.payment',
            [$exception->payment->id, 'redirect' => route('home')]
        );
    }

On the payment confirmation page, the customer will be prompted to enter their credit card info again and perform any additional actions required by Stripe, such as "3D Secure" confirmation. After confirming their payment, the user will be redirected to the URL provided by the `redirect` parameter specified above. Upon redirection, `message` (string) and `success` (integer) query string variables will be added to the URL.

Alternatively, you could allow Stripe to handle the payment confirmation for you. In this case, instead of redirecting to the payment confirmation page, you may [setup Stripe's automatic billing emails](https://dashboard.stripe.com/account/billing/automatic) in your Stripe dashboard. However, if a `IncompletePayment` exception is caught, you should still inform the user they will receive an email with further payment confirmation instructions.

Payment exceptions may be thrown for the following methods: `charge`, `invoiceFor`, and `invoice` on the `Billable` user. When handling subscriptions, the `create` method on the `SubscriptionBuilder`, and the `incrementAndInvoice` and `swapAndInvoice` methods on the `Subscription` model may throw exceptions.

There are currently two types of payment exceptions which extend `IncompletePayment`. You can catch these separately if needed so that you can customize the user experience:

<div class="content-list" markdown="1">

- `PaymentActionRequired`: this indicates that Stripe requires extra verification in order to confirm and process a payment.
- `PaymentFailure`: this indicates that a payment failed for various other reasons, such as being out of available funds.

</div>

<a name="strong-customer-authentication"></a>
## Strong Customer Authentication

If your business is based in Europe you will need to abide by the Strong Customer Authentication (SCA) regulations. These regulations were imposed in September 2019 by the European Union to prevent payment fraud. Luckily, Stripe and Cashier are prepared for building SCA compliant applications.

> {note} Before getting started, review [Stripe's guide on PSD2 and SCA](https://stripe.com/guides/strong-customer-authentication) as well as their [documentation on the new SCA APIs](https://stripe.com/docs/strong-customer-authentication).

<a name="payments-requiring-additional-confirmation"></a>
### Payments Requiring Additional Confirmation

SCA regulations often require extra verification in order to confirm and process a payment. When this happens, Cashier will throw a `PaymentActionRequired` exception that informs you that this extra verification is needed. More info on how to handle these exceptions be found [here](#handling-failed-payments).

#### Incomplete and Past Due State

When a payment needs additional confirmation, the subscription will remain in an `incomplete` or `past_due` state as indicated by its `stripe_status` database column. Cashier will automatically activate the customer's subscription via a webhook as soon as payment confirmation is complete.

For more information on `incomplete` and `past_due` states, please refer to [our additional documentation](#incomplete-and-past-due-status).

<a name="off-session-payment-notifications"></a>
### Off-Session Payment Notifications

Since SCA regulations require customers to occasionally verify their payment details even while their subscription is active, Cashier can send a payment notification to the customer when off-session payment confirmation is required. For example, this may occur when a subscription is renewing. Cashier's payment notification can be enabled by setting the `CASHIER_PAYMENT_NOTIFICATION` environment variable to a notification class. By default, this notification is disabled. Of course, Cashier includes a notification class you may use for this purpose, but you are free to provide your own notification class if desired:

    CASHIER_PAYMENT_NOTIFICATION=Laravel\Cashier\Notifications\ConfirmPayment

To ensure that off-session payment confirmation notifications are delivered, verify that [Stripe webhooks are configured](#handling-stripe-webhooks) for your application and the `invoice.payment_action_required` webhook is enabled in your Stripe dashboard. In addition, your `Billable` model should also use Laravel's `Illuminate\Notifications\Notifiable` trait.

> {note} Notifications will be sent even when customers are manually making a payment that requires additional confirmation. Unfortunately, there is no way for Stripe to know that the payment was done manually or "off-session". But, a customer will simply see a "Payment Successful" message if they visit the payment page after already confirming their payment. The customer will not be allowed to accidentally confirm the same payment twice and incur an accidental second charge.

<a name="stripe-sdk"></a>
## Stripe SDK

Many of Cashier's objects are wrappers around Stripe SDK objects. If you would like to interact with the Stripe objects directly, you may conveniently retrieve them using the `asStripe` method:

    $stripeSubscription = $subscription->asStripeSubscription();

    $stripeSubscription->application_fee_percent = 5;

    $stripeSubscription->save();

You may also use the `updateStripeSubscription` method to update the Stripe subscription directly:

    $subscription->updateStripeSubscription(['application_fee_percent' => 5]);

<a name="testing"></a>
## Testing

When testing an application that uses Cashier, you may mock the actual HTTP requests to the Stripe API; however, this requires you to partially re-implement Cashier's own behavior. Therefore, we recommend allowing your tests to hit the actual Stripe API. While this is slower, it provides more confidence that your application is working as expected and any slow tests may be placed within their own PHPUnit testing group.

When testing, remember that that Cashier itself already has a great test suite, so you should only focus on testing the subscription and payment flow of your own application and not every underlying Cashier behavior.

To get started, add the **testing** version of your Stripe secret to your `phpunit.xml` file:

    <env name="STRIPE_SECRET" value="sk_test_<your-key>"/>

Now, whenever you interact with Cashier while testing, it will send actual API requests to your Stripe testing environment. For convenience, you should pre-fill your Stripe testing account with subscriptions / plans that you may then use during testing.

> {tip} In order to test a variety of billing scenarios, such as credit card denials and failures, you may use the vast range of [testing card numbers and tokens](https://stripe.com/docs/testing) provided by Stripe.
