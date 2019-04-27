<?php
class ControllerExtensionModuleAllXML extends Controller {
  private $categories;
  private $products = array();
  private $image_width = 400;
  private $image_height = 400;
  private $path = '../export/file/';
  private $store_url = 'https://store_url.ua';
  private $categories_aliases = array(
    // category_id => 'new_category_name'
  );

  private $ignore = array();


  public function __construct($registry) {
    parent::__construct($registry);
  }

  private function init($use_aliases = false, $filter_category = false, $category_based = true){
    $this->load->model('catalog/product');
    $this->load->model('catalog/category');
    $this->load->model('catalog/manufacturer');
    $this->load->model('tool/image');
    $this->load->model('localisation/language');
    $this->load->model('localisation/currency');
   
    $this->categories = $this->getCategoriesList($use_aliases, $filter_category);

    if ($category_based){
      foreach($this->categories as $category){
        $this->setProductsArray($this->get_products_by_category($category['category_id']));
      }
    } else {
      $this->setProductsArray($this->get_all_products_list());
    }
    
  }


  public function get_from_price_ua(){
    $this->init();

    $xml = $this->get_xml_price_ua();

    $this->saveXML($xml, 'feed_priceua');

    header('Content-type: text/xml');

    echo $xml;
  }

  public function get_from_hotline(){
    $this->init(true, true, false);

    if (isset($this->request->get['storeid'])){
      $xml = $this->get_xml_hotline($this->request->get['storeid']);

      $this->saveXML($xml, 'feed_hotline');
  
      header('Content-type: text/xml');

      echo $xml;
    } else {
      echo 'NOT SET REQUIRED PARAM STOREID';
    }
  }

  public function get_from_ecatalog(){
    $this->init();

    $xml = $this->get_xml_e_catalog();

    $this->saveXML($xml, 'feed_ecatalog');

    header('Content-type: text/xml');

    echo $xml;
  }

   
  // получаем список категорий array(id => cat)
  private function getCategoriesList($use_aliases = false, $filter_category = false){
    $categories = $this->db->query('SELECT c.category_id as category_id, c.parent_id as parent_id, d.name as name from ' . DB_PREFIX . 'category c LEFT JOIN ' . DB_PREFIX . 'category_description d ON (c.category_id = d.category_id) WHERE status = 1 ORDER BY category_id');
    
    $res = array();

    foreach($categories->rows as $category){
      if ($filter_category && in_array($category['category_id'], $this->ignore)) continue;

      if ($use_aliases){
        $res[$category['category_id']] = $this->get_category_alias($category);
      } else {
        $res[$category['category_id']] = $category;
      }
      
    }

    return $res;
  }


  private function get_xml_e_catalog(){
    $output = '';

    $output .= '<?xml version="1.0" encoding="utf-8"?>';
    $output .= '<yml_catalog date="'. date('Y-m-d H:i') .'" >';
    $output .= '<shop>';

    $output .= '<name>' . $this->config->get('config_name') . '</name>';
    $output .= '<url>' . $this->store_url . '</url>';

    // $output .= '<currencies>';
    // $output .= '<currency id="UAH" rate="1"/>';
    // $output .= '</currencies>';

    $output .= '<catalog>';

    foreach ($this->categories as $category){
      if ((int)$category['parent_id'] === 0){
        $output .= '<category id="'. $category['category_id'] .'">'. $category['name'] .'</category>';
      } else {
        $output .= '<category id="'. $category['category_id'] .'" parentId="'. $category['parent_id'] .'">'. $category['name'] .'</category>';
      }
    }

    $output .= '</catalog>';
    
    $output .= '<items>';

    foreach ($this->products as $product){

      $output .= '<item id="'. $product['product_id'] .'">';
      $output .= '<name><![CDATA['. $product['name'] .']]></name>';
      $output .= '<url>'. $this->url->link('product/product', 'product_id='. $product['product_id']) .'</url>';

      if ($product['special']){
        $output .= '<price>'. $product['special'] .'</price>';
      } else {
        $output .= '<price>'. $product['price'] .'</price>';
      }

      $output .= '<categoryId>'. $product['category_id'] .'</categoryId>';

      if (!empty($product['manufacturer'])){
        $output .= '<vendor><![CDATA['. $product['manufacturer'] .']]></vendor>';
      }

      $output .= '<image>'. $product['image'] .'</image>';

      $description = strip_tags(html_entity_decode($product['description']));

      $output .= '<description><![CDATA['. $description .']]></description>';

      $output .= '</item>';
    }

    $output .= '</items>';

    $output .= '</shop>';
    $output .= '</yml_catalog>';

    return $output;
  }

  private function get_xml_hotline($firm_id){
    $output = '';

    $output .= '<?xml version="1.0" encoding="UTF-8"?>';
    $output .= '<price>';
    $output .= '<date>'.date('Y-m-d H:i').'</date>';
    $output .= '<firmName>'.$this->config->get('config_name').'</firmName>';
    $output .= '<firmId>'. $firm_id .'</firmId>';

    $output .= '<categories>';

    foreach ($this->categories as $category){
      $output .= '<category>';

      $output .= '<id>'. $category['category_id'] .'</id>';

      if ($category['parent_id']) {
        $output .= '<parentId>'. $category['parent_id'] .'</parentId>';
      }
      
      $output .= '<name>'. $category['name'] .'</name>';

      $output .= '</category>';
    }

    $output .= '</categories>';

    $output .= '<items>';

    foreach ($this->products as $product){
      $output .= '<item>';

      $output .= '<id>'. $product['product_id'] .'</id>';
      $output .= '<categoryId>'. $product['category_id'] .'</categoryId>';
      $output .= '<code>'. $product['model'] .'</code>';

      if (!empty($product['manufacturer'])){
        $output .= '<vendor><![CDATA['. $product['manufacturer'] .']]></vendor>';
      }

      $output .= '<name><![CDATA['. $product['name'] .']]></name>';

      $description = strip_tags(html_entity_decode($product['description']));

      $output .= '<description><![CDATA['. $description .']]></description>';

      $output .= '<url>'. $this->url->link('product/product', 'product_id='. $product['product_id']) .'</url>';
      $output .= '<image>'. $product['image'] .'</image>';

      if ($product['special']){
        $output .= '<priceRUAH>'. $product['special'] .'</priceRUAH>';
        $output .= '<oldprice>'. $product['price'] .'</oldprice>';
      } else {
        $output .= '<priceRUAH>'. $product['price'] .'</priceRUAH>';
      }

      $output .= '<param name="Оригинальность">Оригинал</param>';
      $output .= '<param name="Страна изготовления">Корея</param>';
      $output .= '<stock>В наличии</stock>';
      
      $output .= '</item>';
    }

    $output .= '</items>';

    $output .= '</price>';

    return $output;
  }


  private function get_xml_price_ua(){
    $output = '';

    $output .= '<?xml version="1.0" encoding="UTF-8"?>';
    $output .= '<price date="'.date('Y-m-d H:i').'">';
    $output .= '<name>'.$this->config->get('config_name').'</name>';

    $output .= '<catalog>';

    foreach ($this->categories as $category){
      if ((int)$category['parent_id'] == 0){
        $output .= '<category id="'. $category['category_id'] .'">'. $category['name'] .'</category>';
      } else {
        $output .= '<category id="'. $category['category_id'] .'" parentID="'. $category['parent_id'] .'">'. $category['name'] .'</category>';
      }
    }

    $output .= '</catalog>';
    
    $output .= '<items>';

    foreach ($this->products as $product){

      $output .= '<item id="'. $product['product_id'] .'">';
      $output .= '<name><![CDATA['. $product['name'] .']]></name>';
      $output .= '<categoryId>'. $product['category_id'] .'</categoryId>';

      if ($product['special']){
        $output .= '<price>'. $product['special'] .'</price>';
        $output .= '<oldprice>'. $product['special'] .'</oldprice>';
      } else {
        $output .= '<price>'. $product['price'] .'</price>';
      }

      $output .= '<url>'. $this->url->link('product/product', 'product_id='. $product['product_id']) .'</url>';
      $output .= '<image>'. $product['image'] .'</image>';
      
      if (!empty($product['manufacturer'])){
        $output .= '<vendor><![CDATA['. $product['manufacturer'] .']]></vendor>';
      }

      $description = strip_tags(html_entity_decode($product['description']));

      $output .= '<description><![CDATA['. $description .']]></description>';

      $output .= '<param name="Страна изготовления">Корея</param>';

      $output .= '</item>';
    }

    $output .= '</items>';

    $output .= '</price>';

    return $output;
  }


  
  private function saveXML($output, $name){
    $dom = new DOMDocument;
    $dom->preserveWhiteSpace = FALSE;
    $dom->loadXML($output, LIBXML_PARSEHUGE);

    //Save XML as a file
    $dom->save($this->path . $name .'.xml');

    return $output;
  }


  private function get_products_by_category($category_id){
    $sql="SELECT
            p.product_id, category_id, model, pd.name as name, description, p.price as price, p.image,

            (SELECT name FROM ".DB_PREFIX."manufacturer m WHERE m.manufacturer_id= p.manufacturer_id) as manufacturer,

            (SELECT price
              FROM ".DB_PREFIX."product_special ps
              WHERE 
                ps.product_id = p.product_id AND
                ps.customer_group_id = '". 1 ."' AND
                ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND
                (ps.date_end = '0000-00-00' OR ps.date_end > NOW()))
              ORDER BY ps.priority ASC, ps.price ASC
              LIMIT 1) AS special,

            (SELECT s.name FROM ".DB_PREFIX."stock_status s WHERE p.stock_status_id=s.stock_status_id LIMIT 1) as stock_status

            FROM ".DB_PREFIX."product_to_category p2c
                LEFT JOIN ".DB_PREFIX."product p ON (p2c.product_id = p.product_id)
                LEFT JOIN ".DB_PREFIX."product_description pd ON (p.product_id = pd.product_id)
                
            WHERE 
                pd.language_id = '".(int)$this->config->get('config_language_id')."' AND 
                p.status = '1' AND 
                p.quantity > 0 AND
                p.date_available <= NOW() AND 
                p2c.category_id = '".(int)$category_id."'
            
            GROUP BY p.product_id
            ORDER BY p.sort_order ASC, LCASE(pd.name) ASC limit 30";
    

    return $this->db->query($sql)->rows;
  }

  private function get_all_products_list(){

    $sql="SELECT
      p.product_id as product_id, 
      p.model as model, 
      pd.name as name, 
      pd.description as description, 
      p.price as price, 
      p.image as image,

      (SELECT name FROM ".DB_PREFIX."manufacturer m WHERE m.manufacturer_id= p.manufacturer_id) as manufacturer,

      (SELECT price
        FROM ".DB_PREFIX."product_special ps
        WHERE 
          ps.product_id = p.product_id AND
          ps.customer_group_id = '". 1 ."' AND
          ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND
          (ps.date_end = '0000-00-00' OR ps.date_end > NOW()))
        ORDER BY ps.priority ASC, ps.price ASC
        LIMIT 1) AS special,

      (SELECT s.name FROM ".DB_PREFIX."stock_status s WHERE p.stock_status_id=s.stock_status_id LIMIT 1) as stock_status,

      (SELECT cp.category_id as category_id FROM ".DB_PREFIX."category_path cp
        LEFT JOIN ".DB_PREFIX."product_to_category p2c ON (p2c.category_id = cp.category_id)
        WHERE p2c.product_id = p.product_id AND p2c.category_id = cp.category_id ORDER BY cp.level DESC LIMIT 1) as category_id


      FROM ".DB_PREFIX."product p
          LEFT JOIN ".DB_PREFIX."product_description pd ON (p.product_id = pd.product_id)
          
      WHERE 
          pd.language_id = '".(int)$this->config->get('config_language_id')."' AND 
          p.status = '1' AND 
          p.quantity > 0 AND
          p.date_available <= NOW()
      
      
      ORDER BY p.sort_order ASC, LCASE(pd.name) ASC";


    return $this->db->query($sql)->rows;
  }


  private function setProductsArray($products){
    foreach($products as $product){
      if (!isset($this->categories[$product['category_id']])) continue;

      $this->products[] = array(
        'product_id' => $product['product_id'],
        'name' => $product['name'],
        'description' => $product['description'],
        'model' => $product['model'],
        'manufacturer' => $product['manufacturer'],
        'image' => $this->model_tool_image->resize($product['image'], $this->image_width, $this->image_height),
        'price' => $product['price'],
        'stock_status' => $product['stock_status'],
        'category_id' => $product['category_id'],
        'special' => $product['special']
      );
    }
  }


  private function get_category_alias($category){
    if (isset($this->categories_aliases[$category['category_id']])){
      $category['name'] = $this->categories_aliases[$category['category_id']];
    }

    return $category;
  }
  
}