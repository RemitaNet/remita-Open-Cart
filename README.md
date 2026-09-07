```python
import base64

readme_content = """# Remita Pay Payment Gateway Extension for OpenCart 4.x

A robust, enterprise-grade OpenCart 4.x payment gateway extension that integrates the **Remita Payment Engine API**. This extension allows online merchants to securely accept payments on their storefronts, automatically generating dynamic checkout URLs via a secure backend cURL API invocation and seamlessly redirecting customers to finalize payments.

---

## Table of Contents
- [Key Features](#key-features)
- [Architecture & MVC-L Pattern](#architecture--mvc-l-pattern)
- [Plugin Directory Tree](#plugin-directory-tree)
- [Detailed Code Component Implementations](#detailed-code-component-implementations)
  - [1. Metadata & Manifest File](#1-metadata--manifest-file)
  - [2. Admin Panel Module Components](#2-admin-panel-module-components)
  - [3. Catalog Storefront Components](#3-catalog-storefront-components)
- [How to Package the Extension](#how-to-package-the-extension)
- [Deployment & Configuration Steps](#deployment--configuration-steps)
- [Technical Considerations & Enhancements](#technical-considerations--enhancements)
- [Troubleshooting & Support](#troubleshooting--support)

---

## Key Features

* **OpenCart 4.x Compatible**: Built natively using the strict namespace design standards introduced in OpenCart 4.x.
* **Secure API Communication**: All sensitive operations, including the transmission of the `secretKey`, are performed server-side via PHP cURL.
* **Dynamic Checkout Redirection**: Captures store order metrics, customer meta-data, totals, and currencies, requesting a multi-channel checkout session on the fly.
* **Sandboxed and Production Ready**: Configurable Base URL allows effortless switching between Remita test beds and production servers.
* **Clean MVC-L Framework Separation**: Dedicated decoupling of presentation (Twig templates) and domain execution logic (Controllers and Language mapping).

---

## Architecture & MVC-L Pattern

OpenCart partitions its internal engine into two operational instances: **Admin** (handling merchant configurations) and **Catalog** (rendering public buyer interactions). This plugin utilizes the Model-View-Controller-Language (MVC-L) design paradigm within both contexts under the `payment` category extension.


```

```
   [ CLIENT BROWSER (Checkout) ] 
                 │
     AJAX Post  │  ▲  JSON Redirect Response
                 ▼  │

```

[ Catalog Controller: remita_pay|send ]
│
Encrypted    │  ▲  Deserialized
cURL POST   ▼  │   JSON Response
[ REMITA PAYMENT GATEWAY API ENGINE ]

```

When a buyer initializes the confirmation stage:
1. The **Catalog Template** presents a native confirmation trigger.
2. Clicking the trigger fires a secure asynchronous AJAX pipeline targeting the Catalog Controller's `send()` action.
3. The **Catalog Controller** isolates order records from memory, constructs the JSON payload, invokes the endpoint using backend cURL with the corresponding keys, extracts the target payment gateway endpoint token, and transparently streams it back to the client interface for immediate browser redirection.

---

## Plugin Directory Tree

Maintain the exact folder architecture outlined below. Variances in directory hierarchy or nomenclature casing will cause structural failures in the OpenCart routing sub-system.

```text
remita_pay/
├── install.json
├── admin/
│   ├── controller/
│   │   └── payment/
│   │       └── remita_pay.php
│   ├── language/
│   │   └── en-gb/
│   │       └── payment/
│   │           └── remita_pay.php
│   └── view/
│       └── template/
│           └── payment/
│               └── remita_pay.twig
└── catalog/
    ├── controller/
    │   └── payment/
    │       └── remita_pay.php
    └── view/
        └── template/
            └── payment/
                └── remita_pay.twig

```

---

## Detailed Code Component Implementations

### 1. Metadata & Manifest File

#### File: `install.json`

Placed strictly at the absolute root of the working repository directory. This provides critical identification data to the OpenCart installation parser.

```json
{
  "name": "Remita Pay Gateway",
  "version": "1.0.0",
  "author": "Your Development Team",
  "link": "[https://yourdomain.com](https://yourdomain.com)",
  "code": "remita_pay"
}

```

---

### 2. Admin Panel Module Components

#### File: `admin/language/en-gb/payment/remita_pay.php`

Encapsulates all localized textual elements for clean string localization.

```php
<?php
$_['heading_title']          = 'Remita Pay';
$_['text_extensions']        = 'Extensions';
$_['text_success']           = 'Success: You have successfully modified Remita Pay settings!';
$_['text_edit']              = 'Edit Remita Pay Configuration';

// Entry Form Labels
$_['entry_base_url']         = 'Remita Base API URL';
$_['entry_secret_key']       = 'Secret Key';
$_['entry_status']           = 'Gateway Status';

// Help Text Hints
$_['help_base_url']          = 'Provide the root API endpoint URL given by Remita (e.g., [https://remitademo.net](https://remitademo.net) or production gateway).';
$_['help_secret_key']        = 'Provide your unique merchant secret authentication key.';

// Error Threshold Messaging
$_['error_permission']       = 'Warning: You do not possess adequate access credentials to modify Remita Pay settings.';

```

#### File: `admin/controller/payment/remita_pay.php`

Manages backend preferences, routing layouts, standard access control checks, and parameter ingestion rules.

```php
<?php
namespace Opencart\Admin\Controller\Extension\RemitaPay\Payment;

class RemitaPay extends \Opencart\System\Engine\Controller {
    private array $error = [];

    public function index(): void {
        // Load target multi-language strings
        $this->load->language('extension/remita_pay/payment/remita_pay');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        // Verify incoming request and execute transaction serialization rules
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_remita_pay', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        // Assign localized strings into structural template layout array variables
        $data['heading_title']    = $this->language->get('heading_title');
        $data['text_edit']        = $this->language->get('text_edit');
        $data['entry_base_url']   = $this->language->get('entry_base_url');
        $data['entry_secret_key'] = $this->language->get('entry_secret_key');
        $data['entry_status']     = $this->language->get('entry_status');
        $data['help_base_url']    = $this->language->get('help_base_url');
        $data['help_secret_key']  = $this->language->get('help_secret_key');

        // Handle error states safely
        $data['error_warning'] = $this->error['warning'] ?? '';

        // Generate dynamic breadcrumb context trees for navigation visibility
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extensions'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/remita_pay/payment/remita_pay', 'user_token=' . $this->session->data['user_token'], true)
        ];

        // Core Form Routing Enpoints 
        $data['save']   = $this->url->link('extension/remita_pay/payment/remita_pay|save', 'user_token=' . $this->session->data['user_token'], true);
        $data['back']   = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);
        $data['action'] = $this->url->link('extension/remita_pay/payment/remita_pay', 'user_token=' . $this->session->data['user_token'], true);

        // Fetch persisted values across sessions
        $data['payment_remita_pay_base_url']   = $this->config->get('payment_remita_pay_base_url');
        $data['payment_remita_pay_secret_key'] = $this->config->get('payment_remita_pay_secret_key');
        $data['payment_remita_pay_status']     = $this->config->get('payment_remita_pay_status');

        // Attach layout structures to wrap view execution
        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/remita_pay/payment/remita_pay', $data));
    }

    protected function validate(): bool {
        if (!$this->user->hasPermission('modify', 'extension/remita_pay/payment/remita_pay')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }
}

```

#### File: `admin/view/template/payment/remita_pay.twig`

Defines the merchant administration interface styled on Bootstrap classes mapping cleanly into standard OpenCart admin wrappers.

```html
{{ header }}{{ column_left }}
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="float-end">
        <button type="submit" form="form-payment" data-bs-toggle="tooltip" title="Save" class="btn btn-primary"><i class="fa-solid fa-save"></i></button>
        <a href="{{ back }}" data-bs-toggle="tooltip" title="Cancel" class="btn btn-light"><i class="fa-solid fa-reply"></i></a>
      </div>
      <h1>{{ heading_title }}</h1>
      <ol class="breadcrumb">
        {% for breadcrumb in breadcrumbs %}
          <li class="breadcrumb-item"><a href="{{ breadcrumb.href }}">{{ breadcrumb.text }}</a></li>
        {% endfor %}
      </ol>
    </div>
  </div>
  <div class="container-fluid">
    {% if error_warning %}
      <div class="alert alert-danger alert-dismissible"><i class="fa-solid fa-circle-exclamation"></i> {{ error_warning }} <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    {% endif %}
    <div class="card">
      <div class="card-header"><i class="fa-solid fa-pencil"></i> {{ text_edit }}</div>
      <div class="card-body">
        <form action="{{ action }}" method="post" id="form-payment" class="form-horizontal">
          
          <div class="row mb-3 required">
            <label class="col-sm-2 col-form-label" for="input-base-url"><span data-bs-toggle="tooltip" title="{{ help_base_url }}">{{ entry_base_url }}</span></label>
            <div class="col-sm-10">
              <input type="text" name="payment_remita_pay_base_url" value="{{ payment_remita_pay_base_url }}" placeholder="[https://remitademo.net](https://remitademo.net)" id="input-base-url" class="form-control" required="required" />
            </div>
          </div>

          <div class="row mb-3 required">
            <label class="col-sm-2 col-form-label" for="input-secret-key"><span data-bs-toggle="tooltip" title="{{ help_secret_key }}">{{ entry_secret_key }}</span></label>
            <div class="col-sm-10">
              <input type="password" name="payment_remita_pay_secret_key" value="{{ payment_remita_pay_secret_key }}" placeholder="YOUR_SECRET_KEY" id="input-secret-key" class="form-control" required="required" />
            </div>
          </div>

          <div class="row mb-3">
            <label class="col-sm-2 col-form-label" for="input-status">{{ entry_status }}</label>
            <div class="col-sm-10">
              <select name="payment_remita_pay_status" id="input-status" class="form-select">
                <option value="1" {% if payment_remita_pay_status == '1' %}selected="selected"{% endif %}>Enabled</option>
                <option value="0" {% if payment_remita_pay_status == '0' %}selected="selected"{% endif %}>Disabled</option>
              </select>
            </div>
          </div>

        </form>
      </div>
    </div>
  </div>
</div>
{{ footer }}

```

---

### 3. Catalog Storefront Components

#### File: `catalog/controller/payment/remita_pay.php`

Manages rendering the "Pay via Remita" block within checking screens and structures the payload calculation to fetch runtime endpoints from Remita servers.

```php
<?php
namespace Opencart\Catalog\Controller\Extension\RemitaPay\Payment;

class RemitaPay extends \Opencart\System\Engine\Controller {
    
    public function index(): string {
        // Enforce basic module operational state verification checking profiles
        if (!$this->config->get('payment_remita_pay_status')) {
            return '';
        }
        return $this->load->view('extension/remita_pay/payment/remita_pay');
    }

    public function send(): void {
        $json = [];

        // Check if an order session exists
        if (!isset($this->session->data['order_id'])) {
            $json['error'] = 'Your session has expired. Please refresh and try again.';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        if (!$order_info) {
            $json['error'] = 'Invalid order reference exception encountered.';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        // Pull configuration data elements safely from framework environments
        $base_url   = rtrim($this->config->get('payment_remita_pay_base_url'), '/');
        $secret_key = $this->config->get('payment_remita_pay_secret_key');

        // Compile payload. Amount conversion strategy assumes currency base format rules mapping (e.g., Kobo)
        $order_amount = (int)round($order_info['total'] * 100);

        $payload = [
            'firstName'         => (string)$order_info['firstname'],
            'lastName'          => (string)$order_info['lastname'],
            'email'             => (string)$order_info['email'],
            'phoneNumber'       => (string)$order_info['telephone'],
            'paymentIdentifier' => 'oc-' . $order_info['order_id'] . '-' . time(),
            'currency'          => (string)$order_info['currency_code'],
            'narration'         => 'OpenCart Order Reference #' . $order_info['order_id'],
            'amount'            => $order_amount,
            'returnUrl'         => $this->url->link('checkout/success', '', true)
        ];

        // Dispatch request via standard cURL implementation
        $ch = curl_init($base_url . '/api/v1/payment/charge');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'secretKey: ' . $secret_key
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            $json['error'] = 'Gateway connectivity error: ' . $curl_error;
        } else {
            $response_data = json_decode($response, true);
            
            if ($http_code === 200 && !empty($response_data['checkoutUrl'])) {
                // Pass order configuration confirmation flags back inside JSON standard arrays
                $json['redirect'] = $response_data['checkoutUrl'];
            } elseif (!empty($response_data['message'])) {
                $json['error'] = 'API Response Exception: ' . $response_data['message'];
            } else {
                $json['error'] = 'Gateway returned an anomalous response code: ' . $http_code;
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}

```

#### File: `catalog/view/template/payment/remita_pay.twig`

Defines the client wrapper injection block. Handled using native jQuery bindings built standard into the checkout runtime architecture of the core framework.

```html
<div class="d-inline-block pt-2 pb-2 w-100 text-end">
  <button type="button" id="button-confirm" class="btn btn-primary">Pay via Remita</button>
</div>

<script type="text/javascript"><!--
$('#button-confirm').on('click', function() {
    $.ajax({
        url: 'index.php?route=extension/remita_pay/payment/remita_pay|send',
        type: 'post',
        dataType: 'json',
        beforeSend: function() {
            $('#button-confirm').prop('disabled', true).addClass('loading');
            $('.alert-dismissible').remove();
        },
        complete: function() {
            $('#button-confirm').prop('disabled', false).removeClass('loading');
        },
        success: function(json) {
            if (json['error']) {
                $('#button-confirm').closest('.d-inline-block').before('<div class="alert alert-danger alert-dismissible"><i class="fa-solid fa-circle-exclamation"></i> ' + json['error'] + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
            }
            if (json['redirect']) {
                location = json['redirect'];
            }
        },
        error: function(xhr, ajaxOptions, thrownError) {
            alert('A critical communication exception occurred: ' + thrownError);
            console.log(xhr.responseText);
        }
    });
});
//--></script>

```

---

## How to Package the Extension

OpenCart's internal Extension Installer system enforces strict formatting guidelines for automated deployments.

1. Locate your source root development directory where `install.json` resides.
2. Select the items **inside** the root directory: `install.json`, the `admin` folder, and the `catalog` folder.
   *(Crucial step: Do not zip the parent folder itself. The `install.json` file must reside in the flat architectural root of the compressed file).*
3. Compress these files into a standard `.zip` file.
4. Rename your file archive using the suffix `.ocmod.zip`. For instance: `remita_pay.ocmod.zip`.

---

## Deployment & Configuration Steps

Follow these step-by-step instructions to successfully deploy your new Remita plugin:

### Step 1: Uploading the Archive to OpenCart

1. Log into your **OpenCart Admin Dashboard**.
2. Navigate to the left structural sidebar menu and click **Extensions** > **Installer**.
3. Click the blue **Upload** button located in the upper-right region of the administration section.
4. Browse your local files, select your packaged `remita_pay.ocmod.zip` file, and upload it.
5. Wait for the green confirmation progress bar to indicate processing completeness. Your module metadata profile will now populate the installation history logs.

### Step 2: System Activation Initialization

1. In the sidebar, navigate to **Extensions** > **Extensions**.
2. Open the dropdown selector menu titled **Choose the extension type** and select **Payments**.
3. Scroll through the grid to locate **Remita Pay**.
4. Click the green **Install** button adjacent to the extension row to initialize base database table permissions.

### Step 3: API Key & Endpoint Configuration Mapping

1. Click the blue **Edit** (Pencil) button on the **Remita Pay** line profile.
2. Under **Remita Base API URL**, insert the target API path environment endpoint (e.g., `https://remitademo.net` during active development phases, transitioning to the production endpoint for live payment settlement).
3. Under **Secret Key**, securely paste your profile integration key token generated from your merchant dashboard settings console.
4. Toggle the **Gateway Status** dropdown selector config item to **Enabled**.
5. Click the **Save** (Floppy Disk) icon in the absolute top-right frame boundary area of your dashboard console viewport to write variables directly into system configurations.

### Step 4: Verification Sandbox System Checks

1. Navigate to your frontend storefront, assemble items in a shopping cart, and proceed directly through the standardized system checkout funnel steps.
2. Upon arriving at the **Payment Method** section, verify that **Remita Pay** shows up as an active billing gateway utility option.
3. Advance to the configuration confirmation layout screen and click **Pay via Remita**.
4. Confirm that the application fires an invisible network handshake request, retrieves a redirection string, and takes you away from your base storefront URL to the targeted secure checkout screen environment.

---

## Technical Considerations & Enhancements

* **Currency Interceptors**: This extension passes dynamic storefront codes natively (e.g. `NGN`, `USD`). Ensure your merchant configurations on Remita are set up to handle the exact currency units initialized by your store settings.
* **Webhook Listeners (Callback Synchronization)**: While the `returnUrl` handles customer browser redirection post-payment, implementing a custom API endpoint in `catalog/controller/payment/remita_pay|callback` is highly recommended. This allows Remita's background servers to hit your store directly via webhooks to update order statuses even if a customer closes their browser before returning.
* **Decimal Truncation Safety**: The calculation snippet explicitly uses standard roundcasting: `(int)round($order_info['total'] * 100)`. This handles the integer formats required by most major financial APIs to completely mitigate floating-point math rounding deviations during transactional value parsing.

---

## Troubleshooting & Support

* **Blank White Screen or 500 Errors?** Ensure your server has the PHP `curl` extension active. Check your web server's error logs (`error.log`) or OpenCart's log dashboard under **System > Maintenance > Error Logs**.
* **Namespace Resolution Invalidation Exceptions**: Ensure you are running an explicit OpenCart v4.x release ecosystem. Older environments (v3.x or v2.x) do not natively understand PHP namespaces and will reject the Controller initialization protocols.
* **Redirection Failures**: Inspect network logs inside your browser console web inspect tools to see the raw output from the `send()` method. If Remita returns an authentication failure, double-check your API key strings and make sure there are no trailing slashes on your Base URL field input.
  """

with open("README.md", "w", encoding="utf-8") as f:
f.write(readme_content.strip())

print("README.md written successfully.")

```

```text?code_stdout&code_event_index=1
README.md written successfully.


```

Your Markdown documentation file is ready.


The `README.md` file has been fully generated and structured specifically around the OpenCart 4.x extension architecture requirements.

### Key Elements Covered in the Guide:

1. **The MVC-L Pipeline Workflow:** A thorough conceptual architecture breakdown illustrating how the storefront frontend hands variables off asynchronously via jQuery/AJAX, transforms total values to integer decimals (Cents/Kobo conversions), sends payload states directly to the backend through cURL, and handles the resulting redirection response.
2. **Complete Code Blueprint Implementations:** Concrete, production-ready code examples covering the `install.json` metadata manifest file, localized language scripts (`en-gb`), controllers for both administrative panel adjustments and customer-facing interactions, and theme layouts (`.twig`).
3. **Packaging Constraints:** Step-by-step instructions details on how to select and zip items *inside* the development directory to ensure the OpenCart extraction engine reads the manifest properly at runtime.
4. **Step-by-step Deployment Guide:** Granular implementation procedures guiding merchants from uploading the finalized `.ocmod.zip` bundle, assigning permissions, updating API keys, mapping development sandboxes vs live gateways, to executing manual validation transaction test checks.#   r e m i t a - O p e n - C a r t  
 