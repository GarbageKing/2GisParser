<?php


class CParser
{

    //const BASE_URL = 'https://2gis.ua/dnepropetrovsk/rubrics';
    //const BASE_URL = 'https://2gis.ua/odessa/rubrics';
    //const BASE_URL = 'https://2gis.ua/kharkov/rubrics';
    //const BASE_URL = 'https://2gis.ua/donetsk/rubrics';
    const BASE_URL = 'https://2gis.ua/kiev/rubrics';
    
    protected $_allInfo = [];
    
    /** Main function  */
    public function run()
    {               
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        set_time_limit(0);
        ini_set('memory_limit','-1');
        
        $categories = $this->getCategories();
        
        foreach($categories as $category){
            
        $subCategories = $this->getSubCategories($category);
        
            foreach($subCategories as $subCat)
            {
               $companies = $this->getCompanies($subCat);
               
               foreach($companies as $company)
               {   
                   $this->getCompInfo($company);
                  
               }
               
            }
        
        }            
           
    }

    public function getPageContent($url)
    {
        $ch = curl_init();
        $uagent = "Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.9.0.8) Gecko/2009032609 Firefox/3.0.8";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_USERAGENT, $uagent);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $mart = curl_exec($ch);
        curl_close($ch);

        return $mart;
    }
    
    /**
     * @return array
     */
    public function getCategories()
    {
        $html = $this->getPageContent(self::BASE_URL);
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        
        $cats = $dom->getElementsByTagName('li');
        
        $catLinks = [];        
               
        foreach ($cats as $cat) {
                
        if (strpos($cat->getAttribute('class'), 'rubricsList__listItem') !== false){
        
        $catLink = $cat->childNodes[0]->childNodes[0]->attributes['href']->value;         
        if (strpos($catLink, 'http') == false)
        {
            $catLink = 'https://2gis.ua' . $catLink;
        }
        
        $catLinks[] = $catLink;
        }
        
        }                 
        
        return $catLinks;
        
    }
    
    public function getSubCategories($category)
    {
                
        $html = $this->getPageContent(self::BASE_URL.$category);
        
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        
        $subCats = $dom->getElementsByTagName('li');
        
        $subCatLinks = [];        
               
        foreach ($subCats as $subCat) {
                
        if (strpos($subCat->getAttribute('class'), 'rubricsList__listItem') !== false){
        
        $howMuch = $subCat->childNodes[1]->nodeValue;
        $howMuch = explode(' ', $howMuch);
        $howMuch = ceil($howMuch[0]/12);
        
        $arrStr = explode('/', $subCat->childNodes[0]->childNodes[0]->attributes['href']->value);         
               
        for($i=1; $i<=$howMuch; $i++){
        $newHref = self::BASE_URL.'/'.$arrStr[2].'/'.$arrStr[3].'/'.$arrStr[4].'/'
           .$arrStr[5].'/'.$arrStr[6].'/'.$arrStr[7].'/';
        $string = explode('tab', $newHref);
        $newHref = $string[0].'page/'.$i;
        
        $subCatLinks[] = $newHref;
        }
        }
        
        }                 
       
        return $subCatLinks;
        
    }
    
    public function getCompanies($subCat)
    {
        
        $html = $this->getPageContent($subCat);
        $dom = new DOMDocument();
        @$dom->loadHTML($html);  
        
        $comps = $dom->getElementsByTagName('a');
        
        $compLinks = [];        
               
        foreach ($comps as $comp) {
                
        if (strpos($comp->getAttribute('class'), 'miniCard__headerTitleLink') !== false){
        
        $compLinks[] = 'https://2gis.ua' . $comp->attributes['href']->value;         
        }
        
        if (strpos($comp->getAttribute('class'), 'mediaMiniCard__link') !== false){
        
        $compLinks[] = 'https://2gis.ua' . $comp->attributes['href']->value;         
        }
        
        }                 
        
        return $compLinks;
        
    }
    
    public function getCompInfo($company)
    {
        
        $infoArr = [];
        
        $html = $this->getPageContent($company);
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        
        $names = $dom->getElementsByTagName('h1');
        
        foreach ($names as $name) {       
        
            $infoArr['companyName'] = $name->nodeValue;         
        }
        
        $contacts = $dom->getElementsByTagName('div');
        
        foreach ($contacts as $contact) { 
            
            if (strpos($contact->getAttribute('data-module'), 'contact') !== false){
            
                $str = $contact->nodeValue;
                $str = str_replace('+', ' +', $str);
                $str = str_replace('Пожалуйста, скажите, что узнали номер в 2ГИС', ' ', $str);
                $infoArr['companyContact'] = $str; 
            }  
        
            if (strpos($contact->getAttribute('data-module'), 'mediaContacts') !== false){
           
            $str = $contact->nodeValue;
            $str = str_replace('+', ' +', $str);
            $str = str_replace('Пожалуйста, скажите, что узнали номер в 2ГИС', ' ', $str);
            $str = str_replace('Все контакты', ' ', $str);
            $str = str_replace('факс', ' ', $str);
            
            $infoArr['companyContact'] = $str; 
            }                 
            
        }
        
        $this->_allInfo[] = $infoArr;
        
        $fp = fopen('CompaniesKiev.csv', 'a');
	
        $lineArr[] = $infoArr['companyName'];
        $lineArr[] = $infoArr['companyContact'];
        
        fputcsv($fp, $lineArr);
	fclose($fp);          
    }

}


