<?php

class ControllerExtensionPaymentUapay extends Controller
{
    private $error = array();

    public function index()
    {
        $this->load->language('extension/payment/uapay');
        $this->document->setTitle($this->language->get('heading_title'));
        $data = $this->language->all();

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->load->model('setting/setting');

            $this->model_setting_setting->editSetting('payment_uapay', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('extension/payment/uapay', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['client_id'])) {
            $data['error_client_id'] = $this->error['client_id'];
        } else {
            $data['error_client_id'] = '';
        }

        if (isset($this->error['secret_key'])) {
            $data['error_secret_key'] = $this->error['secret_key'];
        } else {
            $data['error_secret_key'] = '';
        }

        if (isset($this->error['redirect_url'])) {
            $data['error_redirect_url'] = $this->error['redirect_url'];
        } else {
            $data['error_redirect_url'] = '';
        }

        if (isset($this->error['order_status'])) {
            $data['error_order_status'] = $this->error['order_status'];
        } else {
            $data['error_order_status'] = '';
        }

        if (isset($this->error['order_status_complete_id'])) {
            $data['error_order_status_complete_id'] = $this->error['order_status_complete_id'];
        } else {
            $data['error_order_status_complete_id'] = '';
        }
        if (isset($this->error['order_status_failure_id'])) {
            $data['error_order_status_failure_id'] = $this->error['order_status_failure_id'];
        } else {
            $data['error_order_status_failure_id'] = '';
        }
        if (isset($this->error['order_status_listen'])) {
            $data['error_order_status_listen'] = $this->error['order_status_listen'];
        } else {
            $data['error_order_status_listen'] = '';
        }

        if (isset($this->error['order_status_auth_id'])) {
            $data['error_order_status_auth_id'] = $this->error['order_status_auth_id'];
        } else {
            $data['error_order_status_auth_id'] = '';
        }

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('extension/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/uapay', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/payment/uapay', 'user_token=' . $this->session->data['user_token'], true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $data = $this->prepareSettings($data);

        $data['user_token'] = $this->session->data['user_token'];

        $this->response->setOutput($this->load->view('extension/payment/uapay', $data));

    }

    public function prepareSettings($data)
    {
        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        if (isset($this->request->post['payment_uapay_geo_zone_id'])) {
            $data['payment_uapay_geo_zone_id'] = $this->request->post['payment_uapay_geo_zone_id'];
        } else {
            $data['payment_uapay_geo_zone_id'] = $this->config->get('payment_uapay_geo_zone_id');
        }

        if (isset($this->request->post['payment_uapay_sort_order'])) {
            $data['payment_uapay_sort_order'] = $this->request->post['payment_uapay_sort_order'];
        } else {
            $data['payment_uapay_sort_order'] = $this->config->get('payment_uapay_sort_order');
        }

        if (isset($this->request->post['payment_uapay_client_id'])) {
            $data['payment_uapay_client_id'] = $this->request->post['payment_uapay_client_id'];
        } else {
            $data['payment_uapay_client_id'] = $this->config->get('payment_uapay_client_id');
        }
        if (isset($this->request->post['payment_uapay_secret_key'])) {
            $data['payment_uapay_secret_key'] = $this->request->post['payment_uapay_secret_key'];
        } else {
            $data['payment_uapay_secret_key'] = $this->config->get('payment_uapay_secret_key');
        }
        if (isset($this->request->post['payment_uapay_status'])) {
            $data['payment_uapay_status'] = $this->request->post['payment_uapay_status'];
        } else {
            $data['payment_uapay_status'] = $this->config->get('payment_uapay_status');
        }
        if (isset($this->request->post['payment_uapay_total'])) {
            $data['payment_uapay_total'] = $this->request->post['payment_uapay_total'];
        } else {
            $data['payment_uapay_total'] = $this->config->get('payment_uapay_total');
        }
        if (isset($this->request->post['payment_uapay_order_status_complete_id'])) {
            $data['payment_uapay_order_status_complete_id'] = $this->request->post['payment_uapay_order_status_complete_id'];
        } else {
            $data['payment_uapay_order_status_complete_id'] = $this->config->get('payment_uapay_order_status_complete_id');
        }
        if (isset($this->request->post['payment_uapay_order_status_failure_id'])) {
            $data['payment_uapay_order_status_failure_id'] = $this->request->post['payment_uapay_order_status_failure_id'];
        } else {
            $data['payment_uapay_order_status_failure_id'] = $this->config->get('payment_uapay_order_status_failure_id');
        }
        if (isset($this->request->post['payment_uapay_order_status_listen'])) {
            $data['payment_uapay_order_status_listen'] = $this->request->post['payment_uapay_order_status_listen'];
        } else {
            $data['payment_uapay_order_status_listen'] = $this->config->get('payment_uapay_order_status_listen');
        }

        if (isset($this->request->post['payment_uapay_redirect_url'])) {
            $data['payment_uapay_redirect_url'] = $this->request->post['payment_uapay_redirect_url'];
        } else {
            $data['payment_uapay_redirect_url'] = $this->config->get('payment_uapay_redirect_url');
        }

        if (isset($this->request->post['payment_uapay_type_payment'])) {
            $data['payment_uapay_type_payment'] = $this->request->post['payment_uapay_type_payment'];
        } else {
            $data['payment_uapay_type_payment'] = $this->config->get('payment_uapay_type_payment');
        }
        $this->load->library('UaPayApi');
        if (strcasecmp($data['payment_uapay_type_payment'], UaPayApi::OPERATION_PAY) == 0) {
            $data['flag_type_payment'] = 1;
        } else {
            $data['flag_type_payment'] = 0;
        }

        if (isset($this->request->post['payment_uapay_test_mode'])) {
            $data['payment_uapay_test_mode'] = $this->request->post['payment_uapay_test_mode'];
        } else {
            $data['payment_uapay_test_mode'] = $this->config->get('payment_uapay_test_mode');
        }
        if (isset($this->request->post['payment_uapay_order_status_auth_id'])) {
            $data['payment_uapay_order_status_auth_id'] = $this->request->post['payment_uapay_order_status_auth_id'];
        } else {
            $data['payment_uapay_order_status_auth_id'] = $this->config->get('payment_uapay_order_status_auth_id');
        }

        return $data;
    }


    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/uapay')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        if (!$this->request->post['payment_uapay_client_id']) {
            $this->error['client_id'] = $this->language->get('error_client_id');
        }
        if (!$this->request->post['payment_uapay_secret_key']) {
            $this->error['secret_key'] = $this->language->get('error_secret_key');
        }

        if ($this->request->post['payment_uapay_redirect_url']) {
            $url = filter_var($this->request->post['payment_uapay_redirect_url'], FILTER_SANITIZE_URL);

            if (empty($url) || filter_var($url, FILTER_VALIDATE_URL) === false)
                $this->error['redirect_url'] = $this->language->get('error_redirect_url');
        }
        
        $complete = (int)$this->request->post['payment_uapay_order_status_complete_id'];

        $auth = (int)$this->request->post['payment_uapay_order_status_auth_id'];

        $fail = (int)$this->request->post['payment_uapay_order_status_failure_id'];
        if ($complete == $fail || $complete == $auth || $auth == $fail) {
            $this->error['order_status'] = $this->language->get('error_order_status');
        }
        if(in_array($auth, $this->request->post['payment_uapay_order_status_listen'])
            || in_array($complete, $this->request->post['payment_uapay_order_status_listen'])
            ||in_array($fail, $this->request->post['payment_uapay_order_status_listen'])){
            $this->error['order_status'] = $this->language->get('error_order_status');
        }

        return !$this->error;
    }

    public function install()
    {
        $this->load->model('setting/event');

        $this->model_setting_event->addEvent('uapay',
            'catalog/model/checkout/order/addOrderHistory/before',
            'extension/payment/uapay/uapayRefund'
        );

        $this->load->model('extension/payment/uapay');
        $this->model_extension_payment_uapay->install();
    }

    public function uninstall()
    {
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEvent('uapay');

        $this->load->model('extension/payment/uapay');
        $this->model_extension_payment_uapay->uninstall();
    }
}