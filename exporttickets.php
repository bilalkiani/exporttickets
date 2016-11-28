<?php

/**
 * FilesystemStorage plugin
 *
 * Allows attachment data to be written to the disk rather than in the
 * database
 */
require_once (INCLUDE_DIR . 'class.plugin.php');
require_once (INCLUDE_DIR . 'class.signal.php');
require_once (INCLUDE_DIR . 'class.app.php');
require_once (INCLUDE_DIR . 'class.dispatcher.php');
require_once (INCLUDE_DIR . 'class.dynamic_forms.php');
require_once (INCLUDE_DIR . 'class.osticket.php');
class ExportTicketsPluginConfig extends PluginConfig {

    // Provide compatibility function for versions of osTicket prior to
    // translation support (v1.9.4)
    function translate() {
        if (!method_exists('Plugin', 'translate')) {
            return array(
                function($x) { return $x; },
                function($x, $y, $n) { return $n != 1 ? $y : $x; },
            );
        }
        return Plugin::translate('storage-fs');
    }

    function getOptions() {
        list($__, $_N) = self::translate();
        return array(
            // 'uploadpath' => new TextboxField(array(
            //     'label'=>$__('Base folder for attachment files'),
            //     'hint'=>$__('The path must already exist and be writeable by the
            //         web server. If the path starts with neither a `/` nor a
            //         drive letter, the path will be assumed to be relative to
            //         the root of osTicket'),
            //     'configuration'=>array('size'=>60, 'length'=>255),
            //     'required'=>true,
            // )),        
            'export_pdf_format' => new BooleanField(array(
                'id'    => 'export_pdf_format',
                'label' => 'Export Pdf',
                'configuration' => array(
                    'desc' => 'Enable Pdf Export')                
            )),
            'export_txt_format' => new BooleanField(array(
                'id'    => 'export_txt_format',
                'label' => 'Export Txt',
                'configuration' => array(
                    'desc' => 'Enable Txt Export')                
            )),
            'export_word_format' => new BooleanField(array(
                'id'    => 'export_word_format',
                'label' => 'Export Word',
                'configuration' => array(
                    'desc' => 'Enable Word Export')                
            )),
                       
    );
    }

    function pre_save(&$config, &$errors) {
        global $msg;

        if (!$errors)
            $msg = 'Configuration updated successfully';

        return true;
    }
}

class ExportTicketsPlugin extends Plugin {
    var $config_class = 'ExportTicketsPluginConfig';
    
    function bootstrap() {
        $this->createStaffMenu();
        //$this->createFrontMenu();
        $this->createAdminMenu();
    }

    /**
     * Creates menu links in the staff backend.
     */
    function createStaffMenu() {
        Application::registerStaffApp ( 'Staff app', 'bilal.php', array (
                iconclass => 'faq-categories' 
        ) );
    }
    
    /**
     * Creates menu link in the client frontend.
     * Useless as of OSTicket version 1.9.2.
     */
    function createFrontMenu() {
        Application::registerClientApp ( 'Export Tickets', 'client_front.php', array (
                iconclass => 'faq-categories' 
        ) );
    }
    function createAdminMenu() {
        Application::registerAdminApp ( 'Admin app', 'bilal.php', array (
                iconclass => 'equipment' 
        ) );
    }
    
}

$test1 = new ExportTicketsPlugin();