<?php

require __DIR__ . '/app/bootstrap.php';

class TestApp extends \Magento\Framework\App\Http implements \Magento\Framework\AppInterface
{
    public function __construct(
        \Magento\Framework\App\Response\Http $response,
        \Magento\Framework\App\Cache\Manager $cacheManager
    ) {
        $this->_response = $response;
        $this->cacheManager = $cacheManager;
    }

    public function objectManager($class){
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        return $objectManager->get($class);
    }

    public function log($message){
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/anonymizer.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($message);
    }

    public function mysql($query){
        try{
            $resource = $this->objectManager('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();
            $connection->query($query);
        }catch(Exception $e){
            $message = "SQL request failed " .$query;
            $this->log($message);
        }
    }

    public function tableExists($table){
        $resource = $this->objectManager('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $query = 'SHOW TABLES LIKE "'.$table.'"';
        $result = count($connection->fetchAll($query));
        return $result;
    }

    public function mysqlChecker($path){
        $resource = $this->objectManager('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $query = "select * from core_config_data where path = '{$path}'";
        $result = $connection->fetchAll($query);
        return count($result);
    }

    public function generateRandomString($length,$str) {
        $characters = $str;
        $charactersLength = strlen($characters);
        $randomString = '';
        if($length >10){
            $length = 10;
        }
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        $randomString = str_replace('.','',$randomString);
        $randomString = str_replace('\'','',$randomString);
        $randomString = str_replace('"','',$randomString);
        $randomString = str_replace(' ','',$randomString);

        return $randomString;
    }

    public function cache(){
        try{
            $this->cacheManager->flush($this->cacheManager->getAvailableTypes());
            $this->cacheManager->clean($this->cacheManager->getAvailableTypes());
            $message = "The cache was cleaned"."\n";
            $this->log($message);
            echo $message;
        }catch(Exception $e){
            $message = "The cache clean failed";
            $this->log($message);
        }

    }

    public function reindex(){
        $indexerFactory = $this->objectManager('Magento\Indexer\Model\IndexerFactory');
        $indexerIds = array(
            'customer_grid',
        );
        foreach ($indexerIds as $indexerId) {
            echo " create index: ".$indexerId."\n";
            $indexer = $indexerFactory->create();
            $indexer->load($indexerId);
            $indexer->reindexAll();
        }
    }

    public function googleAnalytics(){

        try{
            $pathAnalytics = 'google/analytics/active';
            $pathAdWords = 'google/adwords/active';
            $flagAnalytics = $this->mysqlChecker($pathAnalytics);
            $flagAdWords = $this->mysqlChecker($pathAdWords);
            $message = '';
            if($flagAnalytics != 0 && $flagAdWords != 0){
                $queryAnalytics =  "update core_config_data set value = 0 where path = '{$pathAnalytics}'";
                $queryAdWords =  "update core_config_data set value = 0 where path = '{$pathAdWords}'";
                $this->mysql($queryAnalytics);
                $this->mysql($queryAdWords);
                $message = 'The "Google Analytics" and "Google AdWords" were disabled'."\n";
            }elseif($flagAnalytics != 0){
                $queryAnalytics =  "update core_config_data set value = 0 where path = '{$pathAnalytics}'";
                $this->mysql($queryAnalytics);
                $message = 'The "Google Analytics" is disable'."\n";
            }elseif($flagAdWords != 0){
                $queryAdWords =  "update core_config_data set value = 0 where path = '{$pathAdWords}'";
                $this->mysql($queryAdWords);
                $message = 'The "Google AdWords" is disable'."\n";
            }else{
                $message = 'The "Google Analytics" and "Google AdWords" is disable'."\n";
            }


            $this->log($message);
            echo 'The googleAnalytics function was executed successfully'."\n";

        }catch (Exception $e){
            $message = "The googleAnalytics request failed";
            $this->log($message);
        }
    }


    public function changeRobots(){
        try{
            $path = 'design/search_engine_robots/default_robots';
            $flag = $this->mysqlChecker($path);
            if($flag == 0){
                $query = "insert into core_config_data (config_id,scope,scope_id,path,value) values ('','default','0','{$path}','NOINDEX,NOFOLLOW')";
            }else{
                $query = "update core_config_data set value = 'NOINDEX,NOFOLLOW' where path = '{$path}'";
            }
            $this->mysql($query);
            $message = 'The meta "robots" key was changed'."\n";
            $this->log($message);
            echo 'The changeRobots function was executed successfully'."\n";
        }catch(Exception $e){
            $message = "The meta 'robots' request failed";
            $this->log($message);
        }
    }

    public function mysqlFetchAll($table,$where = false){
        try{
            $resource = $this->objectManager('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();
            $flag = $this->tableExists($table);
            if($flag == 0){
                return array();
            }
            $query = "select * from {$table}";
            if($where){
                $query = $query." ".$where;
            }
            $result = $connection->fetchAll($query);
            return $result;
        }catch(Exception $e){
            $message = "mysqlFetchAll request failed";
            $this->log($message);
        }
    }

    public function changeCustomerInfo(){
        try{
            $table = 'customer_entity';
            $salesOrder = 'sales_order';
            $salesOrderGrid = 'sales_order_grid';
            $salesInvoiceGrid = 'sales_invoice_grid';
            $salesShipmentGrid = 'sales_shipment_grid';
            $salesCreditmemoGrid = 'sales_creditmemo_grid';
            $customerGridFlat = 'customer_grid_flat';
            $magentoGiftregistryPerson = 'magento_giftregistry_person';
            $customerAddress = 'customer_address_entity';
            $fetchAll = $this->mysqlFetchAll($table);
            $query = '';
            foreach ($fetchAll as $customer){
                $newEmail = $this->generateRandomString(5,'0123456789').$customer['email'];
                $newFirstName = $this->generateRandomString(strlen($customer['firstname']),$customer['firstname']);
                $newLastName = $this->generateRandomString(strlen($customer['lastname']),$customer['lastname']);
                $query = "update {$table} set email = '{$newEmail}', firstname = '{$newFirstName}' , lastname = '{$newLastName}' where email = '{$customer['email']}'";
                $this->mysql($query);
                $whereCustomerEmail = "where customer_email = '{$customer['email']}'";
                $whereCustomerEmailFlat = "where email = '{$customer['email']}'";
                $whereCustomerAddress = "where parent_id = '{$customer['entity_id']}'";

                try{
                    if(count($this->mysqlFetchAll($customerAddress,$whereCustomerAddress)) >= 1){
                        $address = $this->mysqlFetchAll($customerAddress,$whereCustomerAddress);
                        $address = array_shift($address);
                        $city = $this->generateRandomString(strlen($address['city']),$address['city']);
                        $company = $this->generateRandomString(strlen($address['company']),$address['company']);
                        $street = $this->generateRandomString(strlen($address['street']),$address['street']);
                        $telephone = $this->generateRandomString(strlen($address['telephone']),$address['telephone']);
                        $queryCustomerAddress = "update {$customerAddress} set city = '{$city}', firstname = '{$newFirstName}', lastname = '{$newLastName}', company = '{$company}', street = '{$street}', telephone = '{$telephone}' where parent_id = '{$customer['entity_id']}'";
                        $query .= $queryCustomerAddress.';'."\n";
                        $this->mysql($queryCustomerAddress);
                        //$this->log($queryCustomerAddress);
                    }
                }catch (Exception $e){
                    $message = "SQL request failed " .$queryCustomerAddress;
                    $this->log($message);
                }

                try{
                    if(count($this->mysqlFetchAll($salesOrder,$whereCustomerEmail)) >= 1){
                        $querySalesOrder = "update {$salesOrder} set customer_email = '{$newEmail}', customer_firstname = '{$newFirstName}' , customer_lastname = '{$newLastName}' where customer_email = '{$customer['email']}'";
                        $query .= $querySalesOrder.';'."\n";
                        $this->mysql($querySalesOrder);
                        //$this->log($querySalesOrder);
                    }
                }catch (Exception $e){
                    $message = "SQL request failed " .$querySalesOrder;
                    $this->log($message);
                }

                try{
                    /*sales_order_grid*/
                    if(count($this->mysqlFetchAll($salesOrderGrid,$whereCustomerEmail)) >= 1){
                        $querySalesOrderGrid = "update {$salesOrderGrid} set customer_email = '{$newEmail}', customer_name = '{$newFirstName}' where customer_email = '{$customer['email']}'";
                        $query .= $querySalesOrderGrid.';'."\n";
                        $this->mysql($querySalesOrderGrid);
                        //$this->log($querySalesOrderGrid);
                    }
                }catch (Exception $e){
                    $message = "SQL request failed " .$querySalesOrderGrid;
                    $this->log($message);
                }

                try{
                    /*sales_invoice_grid*/
                    if(count($this->mysqlFetchAll($salesInvoiceGrid,$whereCustomerEmail)) >= 1){
                        $querySalesInvoiceGrid = "update {$salesInvoiceGrid} set customer_email = '{$newEmail}', customer_name = '{$newFirstName}' where customer_email = '{$customer['email']}'";
                        $query .= $querySalesInvoiceGrid.';'."\n";
                        $this->mysql($querySalesInvoiceGrid);
                        //$this->log($querySalesInvoiceGrid);
                    }
                }catch (Exception $e){
                    $message = "SQL request failed " .$querySalesInvoiceGrid;
                    $this->log($message);
                }

                try{
                    /*sales_shipment_grid*/
                    if(count($this->mysqlFetchAll($salesShipmentGrid,$whereCustomerEmail)) >= 1){
                        $querySalesShipmentGrid = "update {$salesShipmentGrid} set customer_email = '{$newEmail}', customer_name = '{$newFirstName}' where customer_email = '{$customer['email']}'";
                        $query .= $querySalesShipmentGrid.';'."\n";
                        $this->mysql($querySalesShipmentGrid);
                        //$this->log($querySalesShipmentGrid);
                    }
                }catch (Exception $e){
                    $message = "SQL request failed " .$querySalesShipmentGrid;
                    $this->log($message);
                }

                try{
                    /*sales_creditmemo_grid*/
                    if(count($this->mysqlFetchAll($salesCreditmemoGrid,$whereCustomerEmail)) >= 1){
                        $querySalesCreditmemoGrid = "update {$salesCreditmemoGrid} set customer_email = '{$newEmail}', customer_name = '{$newFirstName}' where customer_email = '{$customer['email']}'";
                        $query .= $querySalesCreditmemoGrid.';'."\n";
                        $this->mysql($querySalesCreditmemoGrid);
                        //$this->log($querySalesCreditmemoGrid);
                    }
                }catch (Exception $e){
                    $message = "SQL request failed " .$querySalesCreditmemoGrid;
                    $this->log($message);
                }

                try{
                    /*magento_giftregistry_person*/
                    if(count($this->mysqlFetchAll($magentoGiftregistryPerson,$whereCustomerEmailFlat)) >= 1){
                        $querymagentoGiftregistryPerson = "update {$magentoGiftregistryPerson} set email = '{$newEmail}', firstname = '{$newFirstName}', lastname = '{$newLastName}' where email = '{$customer['email']}'";
                        $query .= $querymagentoGiftregistryPerson.';'."\n";
                        $this->mysql($querymagentoGiftregistryPerson);
                        //$this->log($querymagentoGiftregistryPerson);
                    }
                }catch (Exception $e){
                    $message = "SQL request failed " .$querymagentoGiftregistryPerson;
                    $this->log($message);
                }
            }
            $message = 'The customer info was changed'."\n";
            $this->log($message);
            echo 'The changeCustomerInfo function was executed successfully'."\n";
        }catch(Exception $e){
            $message = "The customer_info request failed";
            $this->log($message);
            $this->log($query);
        }
    }

    public function changeEmail($email){

        try{
            $pathArray = array('trans_email/ident_general/email','trans_email/ident_sales/email',
                'trans_email/ident_support/email','trans_email/ident_custom1/email',
                'trans_email/ident_custom2/email','contact/email/recipient_email','sales_email/order/copy_to','sales_email/order_comment/copy_to','sales_email/invoice/copy_to','sales_email/invoice_comment/copy_to',
                'sales_email/shipment/copy_to','sales_email/shipment_comment/copy_to','sales_email/creditmemo/copy_to','sales_email/creditmemo_comment/copy_to');

            foreach ($pathArray as $path){
                $flag = $this->mysqlChecker($path);
                if($flag != 0){
                    $query = "update core_config_data set value = '{$email}' where path = '{$path}'";
                    $this->mysql($query);
                }
            }

            $message = "The emails address were changed";
            $this->log($message);
            echo 'The changeEmail function was executed successfully'."\n";

        }catch (Exception $e){
            $message = "The changeEmail request failed";
            $this->log($message);
        }
    }

    public function mysqlTruncate(){
        try{
            $tableName = array('cron_schedule','session','quote','quote_address',
                'Quote_address_item','quote_id_mask','quote_item','quote_item_option',
                'Quote_payment', 'quote_shipping_rate','newsletter_subscriber',
                'captcha_log','customer_log','Oauth_token_request_log','sendfriend_log',
                'persistent_session');
            foreach ($tableName as $name){
                $name = trim($name);
                if($this->tableExists($name) == 0){
                    $this->log($name.' table does not exist');
                    continue;
                }
                try{
                    $query = "truncate table {$name}";
                    $this->mysql($query);
                }catch (Exception $e){
                    $message = $message = "SQL request failed " .$query;
                    $this->log($message);
                }
            }
            $message = 'The tables were truncated'."\n";
            $this->log($message);
            echo 'The mysqlTruncate function was executed successfully'."\n";
        }catch(Exception $e){
            $message = "Truncate request failed";
            $this->log($message);
        }
    }

    public function launch()
    {

        $this->mysql('SET foreign_key_checks = 0');
        $this->googleAnalytics();
        $this->changeRobots();
        $this->mysqlTruncate();
        $this->changeCustomerInfo();

        $email = 'tester@gmail.com';
        $this->changeEmail($email);

        $this->mysql('SET foreign_key_checks = 1');

        $this->reindex();
        $this->cache();

        return $this->_response;
    }

}

$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$app = $bootstrap->createApplication('TestApp');
$bootstrap->run($app);

?>