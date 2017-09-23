<?php

class ControllerFeedGoogleSitemapMl extends Controller
{
    public function index()
    {

        global $dbcounter, $dbquerylist, $show_queries, $start_time, $selcount, $debug, $all_links;

        $all_links = '';
        $start = microtime(true);

        //CONFIG
        DEFINE('ADD_LANGUAGE_CODE', false);
        //pr($start);


        if (isset($this->request->get['regen']) && $this->request->get['regen'] == 1) {
            $regen = true;
        } else {
            $regen = false;
        }

        //$regen = true;


        if ($this->config->get('google_sitemap_ml_status')) {

            $di = DIR_APPLICATION;
            $rootPath = str_replace("catalog/", "", $di);
            $sitemaps_dir = 'sitemaps/';
            $fname = 'sitemap.xml';

            //Ja fails ir jaunāks par XX (20h) sekundēm, tad atgriežam failu, pretējā gadījumā pārģenerējam.
            if (!$regen && file_exists($rootPath . $fname) && filemtime($rootPath . $fname) > time() - 72000) {

                $this->response->addHeader('Content-Type: application/xml');

                $output = file_get_contents($rootPath . $fname);

                $this->response->setOutput($output);

                return;
            } else {

                $output = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
                $output = '<?xml-stylesheet type="text/xsl" href="' . HTTP_SERVER . 'sitemap.xsl"?>' . "\n";

                $output .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";


                $this->load->model('catalog/product');
                $this->load->model('localisation/language');
                $languages = $this->model_localisation_language->getLanguages();


                foreach ($languages as $lang) {
                    if ($lang['status'] != 1) {
                        continue;
                    }


                    $language = new Language($lang['directory']);
                    $language->load($lang['directory']);
                    $this->registry->set('language', $language);
                    $this->session->data['language'] = $language->get('code');
                    $this->config->set('config_language_id', $lang['language_id']);

                    $output .= '<url>' . "\n";
                    $output .= '<loc>' . $this->url->link('common/home') . '</loc>' . "\n";
                    $output .= '<changefreq>hourly</changefreq>' . "\n";
                    $output .= '<priority>1.0</priority>' . "\n";
                    $output .= '<lastmod>' . date("Y-m-d", time()) . '</lastmod>' . "\n";
                    $output .= '</url>' . "\n";

                    // IF multilanguage!!!
                    $sql_str_1 = " and ul.language_id = '" . $lang['language_id'] . "'";
                    $sql_str_2 = " and ul2.language_id = '0'";
                    $sql_str_1 = $sql_str_2 = '';


                    //Atlasām visas kategorijas jau ar SEO linkiem.
                    $sql = "SELECT c.category_id as category_id, c2.category_id as parent_id, ul.keyword,
                    date_format(c.date_modified, '%Y-%m-%d') as date_modified,
                    ul2.keyword as keyword_neutral
FROM `" . DB_PREFIX . "category` c
left join " . DB_PREFIX . "category c2 on c.parent_id = c2.category_id
left join " . DB_PREFIX . "url_alias ul on ul.query = concat('category_id=', c.category_id) and c.status = 1 $sql_str_1
left join " . DB_PREFIX . "url_alias ul2 on ul2.query = concat('category_id=', c.category_id) $sql_str_2";

                    //prd($sql);

                    $result = $this->db->query($sql);


                    //Uzbbūvējam kategoriju masīvu ar atslēgām.
                    $categories = array();
                    foreach ($result->rows as $val) {

                        //pārbaude, ja pati kategorija norādīta pati sev kā parents:
                        if ($val['parent_id'] == $val['category_id']) {
                            $val['parent_id'] = 0;
                        }
                        $categories[$val['category_id']] = $val;
                    }


                    //prd($categories);

                    //tagad būvēsim KATEGORIJU linkus:

                    //$this->load->model('catalog/category');


                    $output .= $this->getProducts($categories);

                    $output .= $this->getCategories($categories);
                    //prd($output);


                    $this->load->model('catalog/manufacturer');


                    $manufacturers = $this->model_catalog_manufacturer->getManufacturers();

                    foreach ($manufacturers as $manufacturer) {

                        $output .= '<url>' . "\n";
                        $output .= '<loc>' . $this->url->link('product/manufacturer/info',
                                'manufacturer_id=' . $manufacturer['manufacturer_id']) . '</loc>' . "\n";
                        $output .= '<changefreq>weekly</changefreq>' . "\n";
                        $output .= '<priority>0.7</priority>' . "\n";
                        $output .= '<lastmod>' . date("Y-m-d", time()) . '</lastmod>' . "\n";
                        $output .= '</url>' . "\n";
                        $all_links .= $this->url->link('product/manufacturer/info',
                                'manufacturer_id=' . $manufacturer['manufacturer_id']) . "\n";


                        /* $products = $this->model_catalog_product->getSitemapProducts(array('filter_manufacturer_id' => $manufacturer['manufacturer_id']));

                        foreach ($products as $product) {
                            $output .= '<url>'. "\n";
                            $output .= '<loc>' . $this->url->link('product/product', 'manufacturer_id=' . $manufacturer['manufacturer_id'] . '&product_id=' . $product['product_id']) . '</loc>'. "\n";
                            $output .= '<changefreq>hourly</changefreq>'. "\n";
                            $output .= '<priority>1.0</priority>'. "\n";
                            $output .= '<lastmod>' . date( "Y-m-d", time() ) . '</lastmod>'. "\n";
                            $output .= '</url>'. "\n";
                            $all_links1 .= $this->url->link('product/product', 'manufacturer_id=' . $manufacturer['manufacturer_id'] . '&product_id=' . $product['product_id'])  . "\n";
                        }

                        prd($all_links1); */
                    }

                    $this->load->model('catalog/information');
                    //prd( $diff );
                    $informations = $this->model_catalog_information->getInformations();


                    foreach ($informations as $information) {
                        $output .= '<url>' . "\n";
                        $output .= '<loc>' . $this->url->link('information/information',
                                'information_id=' . $information['information_id']) . '</loc>' . "\n";
                        $output .= '<changefreq>weekly</changefreq>' . "\n";
                        $output .= '<priority>0.5</priority>' . "\n";

                        if (isset($information['date_modified']) && date("Y-m-d",
                                strtotime($information['date_modified'])) < (date("Y", time()) - 10)) {
                            $information['date_modified'] = date("Y-m-d", time());
                        } elseif (!isset($information['date_modified'])) {
                            $information['date_modified'] = date("Y-m-d", time());
                        } else {
                            $information['date_modified'] = date("Y-m-d", strtotime($information['date_modified']));
                        }

                        $output .= '<lastmod>' . date("Y-m-d",
                                strtotime($information['date_modified'])) . '</lastmod>' . "\n";
                        $output .= '</url>' . "\n";
                        $all_links .= $this->url->link('information/information',
                                'information_id=' . $information['information_id']) . "\n";
                        //prd($output);

                    }
                }

                $output .= '</urlset>';

                $this->response->addHeader('Content-Type: application/xml');
                //echo "<pre>" . $all_links . "</pre>";

                //die('test');
                /* $output = '';
                $end = microtime(true);
                $diff = $end - $start;
                prd($diff); // */

                $this->saveSitemap($output, $sitemaps_dir, $fname);

                $this->response->setOutput($output);

            }
        } else {
            $this->response->setOutput("Sitemap ML is not enabled. Please, install and enable in Modules!");
        }
    }

    protected function getProducts($categories = array())
    {
        global $all_links;
        $output = '';
        //atlasām visus produktus, jau ar SEO linkiem!
        $products = $this->getSitemapProducts();

        foreach ($products as $product) {
            if (isset($product['product_categories']) && $product['product_categories']) {
                $product_categories = explode(",", $product['product_categories']);
                //pr($product_categories);
            } else {
                $product_categories = array();
            }


            foreach ($product_categories as $product_category) {


                //uzbūvējam visus produkta parent kategoriju linkus
                $category_id = $product_category;
                $i = 0;
                $link_category_seo = '';
                $path = '';
                $broken_seo = false;
                $link = '';
                while ($category_id && $i < 100) {

                    if (isset($categories[$category_id]['keyword']) && $categories[$category_id]['keyword']) {
                        $link_category_seo = "/" . $categories[$category_id]['keyword'] . $link_category_seo;
                    } elseif (isset($categories[$category_id]['keyword_neutral']) && $categories[$category_id]['keyword_neutral']) {
                        $link_category_seo = "/" . $categories[$category_id]['keyword_neutral'] . $link_category_seo;

                        // pr($categories[$category_id]['keyword_neutral']);
                    } else {
                        $broken_seo = true;
                    }

                    //veidojampilno kategorijas linku, kuru izmantosim, ja nebūs atrasts neviens kategorijas seo.
                    $path = $category_id . "_" . $path;


                    if (isset($categories[$category_id]['parent_id'])) {
                        $category_id = $categories[$category_id]['parent_id'];
                    } else {
                        break;
                    }

                    $i++;

                }


                //ja kaut vienai kategorijai izdevās atrast seo linku,
                //tad nonullējam path
                if ($link_category_seo > '/' && !$broken_seo) {
                    $path = '';
                }


                //uzbūvējam Kategorijas linku PILNO ar HTTP:...
                //prd($link_category_seo);
                $link_category_seo = ltrim($link_category_seo, '/');

                if (!$broken_seo) {
                    //Visām kategorijām atasti SEO linki
                    $link = HTTP_SERVER . (ADD_LANGUAGE_CODE ? $this->language->get('code') . '/' : '') . $link_category_seo;
                } else {
                    //vismaz vienai kategorijai nebija seo, tāpēc viss links ir ne seo
                    $link = HTTP_SERVER . (ADD_LANGUAGE_CODE ? $this->language->get('code') . '/' : '');

                }


                //if($product['product_id'] == '316') prd( $path );


                //un ieliekam to "kategoriju masīvā:

                if ($path && $broken_seo) {
                    $path = "&amp;path=" . trim($path, "_");
                } else {
                    $path = '';
                }


                // ja produktam ir atbilstošajā valodā ievadīts SEO links,
                // tad izmantosim to.
                // ja nav, tad pārbaudīsim, vai ir ievdaīts LANGUAGE NEUTRAL keywords
                // ja nav nekā, tad links būs product_id....
                if ($product['keyword'] > ' ') {

                    $link = $link . "/" . $product['keyword'] . ($path ? "?" . $path : '');
                    //pr($link);
                    //pr($product['keyword']);
                } elseif ($product['keyword_neutral'] > ' ') {
                    $link = $link . "/" . $product['keyword_neutral'] . ($path ? "?" . $path : '');
                } else {
                    $link = $link . "?product_id=" . $product['product_id'] . $path;
                }

                //echo "<a href='" . $link . "'>$link</a><br />";


                $output .= '<url>' . "\n";
                $output .= '<loc>' . $link . '</loc>' . "\n";
                $output .= '<changefreq>weekly</changefreq>' . "\n";
                $output .= '<priority>1.0</priority>' . "\n";


                if ($product['date_modified'] < (date("Y", time()) - 10)) {
                    $product['date_modified'] = date("Y-m-d", time());
                }

                $output .= '<lastmod>' . $product['date_modified'] . '</lastmod>' . "\n";
                $output .= '</url>' . "\n";
                $all_links .= $link . "\n";
            }

        }

        //prd();


        return $output;
    }

    protected function getCategories($categories = array())
    {
        global $all_links;
        $output = '';


        foreach ($categories as $category) {
            $link_category_seo = '';
            $category_id = $category['category_id'];
            $i = 0;
            $path = '';
            $broken_seo = false;
            while ($category_id && $i < 100) {

                if (isset($categories[$category_id]['keyword']) && $categories[$category_id]['keyword']) {
                    $link_category_seo = "/" . $categories[$category_id]['keyword'] . $link_category_seo;
                } elseif (isset($categories[$category_id]['keyword_neutral']) && $categories[$category_id]['keyword_neutral']) {
                    $link_category_seo = "/" . $categories[$category_id]['keyword_neutral'] . $link_category_seo;

                } else {
                    //$link_category_seo = '';
                    //break;
                    $broken_seo = true;
                    //echo $category_id; pr( $category );

                }

                //veidojampilno kategorijas linku, kuru izmantosim, ja nebūs atrasts neviens kategorijas seo.
                $path = $category_id . "_" . $path;

                if (isset($categories[$category_id]['parent_id'])) {
                    $category_id = $categories[$category_id]['parent_id'];
                } else {
                    break;
                }
                $i++;
            }

            //print($link_category_seo ."-Broken?: ".$broken_seo."-".$path."--".  $category['category_id'] . "<br/>" );
            $link_category_seo = ltrim($link_category_seo, '/');

            if (!$broken_seo) {
                //Visām kategorijām atasti SEO linki
                $link = HTTP_SERVER . (ADD_LANGUAGE_CODE ? $this->language->get('code') . '/' : '') . $link_category_seo;
            } else {
                //vismaz vienai kategorijai nebija seo, tāpēc viss links ir ne seo
                $link = HTTP_SERVER . (ADD_LANGUAGE_CODE ? $this->language->get('code') . '/' : '') . "index.php?route=product/category&amp;path=" . trim($path,
                        "_");
            }

            $output .= '<url>' . "\n";
            $output .= '<loc>' . $link . '</loc>' . "\n";
            $output .= '<changefreq>weekly</changefreq>' . "\n";
            $output .= '<priority>0.7</priority>' . "\n";

            if ($category['date_modified'] < (date("Y", time()) - 10)) {
                $category['date_modified'] = date("Y-m-d", time());
            }

            $output .= '<lastmod>' . $category['date_modified'] . '</lastmod>' . "\n";
            $output .= '</url>' . "\n";
            $all_links .= $link . "\n";

        }


        return $output;
    }


    private function getSitemapProducts($data = array())
    {
        $start = microtime(true);


        $customer_group_id = $this->config->get('config_customer_group_id');


        $sql = "SELECT

p.product_id, ul.keyword, p2c.category_id, ul2.keyword as keyword_neutral, date_format(p.date_modified, '%Y-%m-%d') as date_modified,
 IFNULL(GROUP_CONCAT(c.category_id ORDER BY c.category_id ASC SEPARATOR ','),'0') product_categories ";

        if (!empty($data['filter_category_id'])) {
            if (!empty($data['filter_sub_category'])) {
                $sql .= " FROM " . DB_PREFIX . "category_path cp";
                //LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (cp.category_id = p2c.category_id)";
            } else {
                // $sql .= " FROM " . DB_PREFIX . "product_to_category p2c";
            }

            if (!empty($data['filter_filter'])) {
                $sql .= " LEFT JOIN " . DB_PREFIX . "product_filter pf ON (p2c.product_id = pf.product_id) LEFT JOIN " . DB_PREFIX . "product p ON (pf.product_id = p.product_id)";
            } else {
                $sql .= " LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)";
            }
        } else {
            $sql .= " FROM " . DB_PREFIX . "product p";
        }


        $sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)";

        $sql .= " LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)";
        $sql .= " LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (p.product_id = p2c.product_id)";
        $sql .= " LEFT JOIN " . DB_PREFIX . "category c ON (p2c.category_id = c.category_id) and c.status = 1 ";


        // if multilanguage
        $s1 = "AND ul.language_id = '" . (int)$this->config->get('config_language_id') . "'";
        $s2 = "AND ul2.language_id = '0'";
        $s3 = "pd.language_status = '1' AND";
        $s1 = $s2 = $s3 = '';

        $sql .= " LEFT JOIN " . DB_PREFIX . "url_alias ul on ul.query = concat('product_id=' , p.product_id)  $s1";
        $sql .= " LEFT JOIN " . DB_PREFIX . "url_alias ul2 on ul2.query = concat('product_id=' , p.product_id) $s2";
        $sql .= " WHERE
                  pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND
                  p.status = '1' AND
                  $s3
                  p.date_available <= '" . date("Y-m-d") . "'
                  AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";


        if (!empty($data['filter_category_id'])) {
            if (!empty($data['filter_sub_category'])) {
                $sql .= " AND cp.path_id = '" . (int)$data['filter_category_id'] . "'";
            } else {
                $sql .= " AND p2c.category_id = '" . (int)$data['filter_category_id'] . "'";
            }

            if (!empty($data['filter_filter'])) {
                $implode = array();

                $filters = explode(',', $data['filter_filter']);

                foreach ($filters as $filter_id) {
                    $implode[] = (int)$filter_id;
                }

                $sql .= " AND pf.filter_id IN (" . implode(',', $implode) . ")";
            }
        }


        if (!empty($data['filter_manufacturer_id'])) {
            $sql .= " AND p.manufacturer_id = '" . (int)$data['filter_manufacturer_id'] . "'";
        }

        $sql .= " GROUP BY p.product_id";

        $query = $this->db->query($sql);
        return $query->rows;
    }


    protected function saveSitemap($theOutput, $sitemaps_dir = 'sitemaps/', $fname = 'sitemap.xml')
    {

        $di = DIR_APPLICATION;
        $rootPath = str_replace("catalog/", "", $di);


        if (!file_exists($rootPath . $sitemaps_dir)) {
            mkdir($rootPath . $sitemaps_dir);
        }

        file_put_contents($rootPath . $fname, $theOutput);
        file_put_contents($rootPath . $sitemaps_dir . $fname, $theOutput);

    }
}