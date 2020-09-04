<?php

namespace SystemReport\Controller;

use SystemReport\SystemReport;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Install\Database;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\HttpKernel\Kernel;

class ReportController extends BaseAdminController
{
    public function viewAction()
    {
        // Capture phpinfo()
        ob_start () ;
        phpinfo (INFO_CONFIGURATION | INFO_MODULES | INFO_ENVIRONMENT) ;
        $phpinfo = ob_get_contents () ;

        $phpinfo = preg_replace("/^.*?\<body\>/is", "", $phpinfo);
        $phpinfo = preg_replace("/<\/body\>.*?$/is", "", $phpinfo);
        ob_end_clean () ;

        // Get the current database name

        // Modules informations
        $modules = [];

        $module_categories = [
            'payment' => Translator::getInstance()->trans("Payment", []),
            'delivery' => Translator::getInstance()->trans("Delivery", []),
            'classic' => Translator::getInstance()->trans("Classic", []),
         ];

        foreach($module_categories as $key=>$category) {
            $modules[$key] = [
                'name' => $module_categories[$key],
                'total' => ModuleQuery::create()->filterByCategory($key)->count(),
                'active' => ModuleQuery::create()->filterByCategory($key)->filterByActivate(1)->count(),
            ];
        }
        // Get Thelia required extensions
        $required_extensions = [
            'pdo_mysql' => "PDO Mysql",
            'openssl' => "OpenSSL",
            'intl' => "Intl",
            'gd' => "GD",
            'curl' => "cURL",
            'calendar' => "Calendar",
            'dom' => "DOM XML"
        ];
        $loaded_extensions = get_loaded_extensions();

        // Get Thelia requirements infos
        $requirements = [
            'safe_mode' => ini_get('safe_mode'),
            'memory_limit' => ini_get('memory_limit'),
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'memory_limit' => ini_get('upload_max_filesize'),
        ];


        return $this->render("systemreport/report", [
                'app_infos' => $this->getAppInfos(),
                'modules' => $modules,
                'phpinfo' => $phpinfo,
                'requirements' => $requirements,
                'loaded_extensions' => $loaded_extensions,
                'required_extensions' => $required_extensions,
            ]
        );
    }

    private function getAppInfos() {
        $con = Propel::getConnection();
        $database = new Database($con);
        // Get Thelia version
        $thelia_version = ConfigQuery::create()->filterByName('thelia_version')->findOne();
        $symfony_version = \Symfony\Component\HttpKernel\Kernel::VERSION;

        $records = $database->execute('SELECT VERSION() as version, DATABASE() as database_name;');
        $database_infos = $records->fetch();
        // foreach ($records as $record) {
        //     $mysql_version = $record['version'];
        //     $database_name = $record['database_name'];
        // }

        $db_tables = $database->execute('
            SELECT TABLE_NAME AS "table_name",
            table_rows AS "nb_rows", round( (
            data_length + index_length
            ) /1024, 2 ) AS "size_kb"
            FROM information_schema.TABLES
            WHERE information_schema.TABLES.table_schema = (select database());'
        );
        $database_tables = [];
        $db_size_kb = 0;
        $nb_tables = 0;
        foreach ($db_tables as $record) {
            $database_tables[] = ['table_name' => $record['table_name'], 'nb_rows' => $record['nb_rows'], 'size_kb' => $record['size_kb']];
            $db_size_kb += $record['size_kb'];
            $nb_tables +=1;
        }

        // Get the application infos
        $app_infos = [
            'thelia_version' => $thelia_version->getValue(),
            'symfony_version' => $symfony_version,
            'database_infos' => $database_infos,
            // 'mysql_version' => $mysql_version,
            // 'database_name' => $database_name,
            'database_tables' => $database_tables,
            'db_size_kb' => $db_size_kb,
            'nb_tables' => $nb_tables,
            'php_version' => phpversion(),
        ];

        return $app_infos;
    }
}