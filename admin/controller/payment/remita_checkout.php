<?php
namespace Opencart\Admin\Controller\Extension\RemitaCheckout\Payment;

class RemitaCheckout extends \Opencart\System\Engine\Controller {
    public function index(): void {
        $this->load->language('extension/remita_pay/payment/remita_pay');
        $this->document->setTitle($this->language->get('heading_title'));

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting('payment_remita_pay', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        $data['payment_remita_pay_secret_key'] = $this->config->get('payment_remita_pay_secret_key');
        $data['payment_remita_pay_base_url'] = $this->config->get('payment_remita_pay_base_url');
        $data['payment_remita_pay_status'] = $this->config->get('payment_remita_pay_status');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/remita_pay/payment/remita_pay', $data));
    }

    protected function validate(): bool {
        return $this->user->hasPermission('modify', 'extension/remita_pay/payment/remita_pay');
    }
}