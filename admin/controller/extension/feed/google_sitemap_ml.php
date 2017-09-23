<?php

class ControllerExtensionFeedGoogleSitemapMl extends Controller
{
    private $error = array();

    public function index()
    {
        $data = $this->language->load('extension/feed/google_sitemap_ml');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('google_sitemap_ml', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/extension', 'token=' . $this->session->data['token'], 'SSL'));
        }

        $data['heading_title'] = $this->language->get('heading_title');


        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text'      => $this->language->get('text_home'),
            'href'      => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => false
        );

        $data['breadcrumbs'][] = array(
            'text'      => $this->language->get('text_feed'),
            'href'      => $this->url->link('extension/feed', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => ' :: '
        );

        $data['breadcrumbs'][] = array(
            'text'      => $this->language->get('heading_title'),
            'href'      => $this->url->link('extension/feed/google_sitemap_ml', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => ' :: '
        );

        $data['action'] = $this->url->link('extension/feed/google_sitemap_ml', 'token=' . $this->session->data['token'], 'SSL');

        $data['cancel'] = $this->url->link('extension/feed', 'token=' . $this->session->data['token'], 'SSL');

        if (isset($this->request->post['google_sitemap_ml_status'])) {
            $data['google_sitemap_ml_status'] = $this->request->post['google_sitemap_ml_status'];
        } else {
            $data['google_sitemap_ml_status'] = $this->config->get('google_sitemap_ml_status');
        }

        $data['data_feed'] = HTTP_CATALOG . 'index.php?route=feed/google_sitemap_ml';

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('feed/google_sitemap_ml', $data));
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/feed/google_sitemap_ml')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->error) {
            return true;
        } else {
            return false;
        }
    }

    public function install()
    {

    }

    public function uninstall()
    {

    }
}