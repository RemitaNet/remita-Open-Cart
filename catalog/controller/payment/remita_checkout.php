<?php
namespace Opencart\Catalog\Controller\Extension\RemitaPay\Payment;

class RemitaCheckout extends \Opencart\System\Engine\Controller {
    public function index(): string {
        // Pass AJAX endpoint link to the button view
        $data['action'] = $this->url->link('extension/remita_checkout/payment/remita_checkout|send', '', true);
        return $this->load->view('extension/remita_checkout/payment/remita_checkout', $data);
    }

    public function send(): void {
        $json = [];

        if (!isset($this->session->data['order_id'])) {
            $json['error'] = 'Missing Order Session';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        // Load the OpenCart order info
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        // Gather plugin settings
        $base_url = rtrim($this->config->get('payment_remita_checkout_base_url'), '/');
        $secret_key = $this->config->get('payment_remita_checkout_secret_key');

        // Map OpenCart order data to your API payload
        $payload = [
            'firstName'         => $order_info['firstname'],
            'lastName'          => $order_info['lastname'],
            'email'             => $order_info['email'],
            'phoneNumber'       => $order_info['telephone'],
            'paymentIdentifier' => 'oc-' . $order_info['order_id'] . '-' . time(),
            'currency'          => $order_info['currency_code'], // e.g., NGN
            'narration'         => 'OpenCart Order #' . $order_info['order_id'],
            'amount'            => (int)($order_info['total'] * 100), // Converted to kobo/cents if API expects integers, change accordingly
            'returnUrl'         => $this->url->link('checkout/success', '', true)
        ];

        // Perform cURL request
        $ch = curl_init($base_url . '/api/v1/payment/charge');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'secretKey: ' . $secret_key
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $result = json_encode($response, true); // Assuming response contains a property like $result['checkoutUrl']

            // Adjust to catch whatever field your specific response returns
            if (isset($result['checkoutUrl'])) {
                $json['redirect'] = $result['checkoutUrl'];
            } else {
                // Fallback decode variation or look for deep array property
                $res_arr = json_decode($response, true);
                if (!empty($res_arr['checkoutUrl'])) {
                    $json['redirect'] = $res_arr['checkoutUrl'];
                } else {
                    $json['error'] = 'Could not obtain checkout URL from API.';
                }
            }
        } else {
            $json['error'] = 'Connection Failure to Remita API.';
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}