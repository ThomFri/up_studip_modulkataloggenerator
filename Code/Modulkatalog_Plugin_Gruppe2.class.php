<?php

/**
 * Class Modulkatalog_Plugin_Gruppe2
 * Start-Klasse des Plugins
 * Definition von Rollen und der Navigation
 */
class Modulkatalog_Plugin_Gruppe2 extends StudIPPlugin implements SystemPlugin
{
    public function __construct()
    {
        parent::__construct();

        /** @noinspection PhpComposerExtensionStubsInspection */
        bindtextdomain('modulkatalog', realpath(__DIR__.'/locale'));

        if(RolePersistence::isAssignedRole($GLOBALS['user']->id, 'Dekanat'))
            $rolle = "dekanat";
        else
            $rolle = $GLOBALS['perm']->get_perm();

        switch ($rolle){
            case "dozent":
                $navigation = new Navigation($this->getPluginName(), PluginEngine::getURL($this, array(), 'dozent/ects'));
                $navigation->addSubNavigation("ects", new Navigation("Eigene ECTS-Liste erzeugen", PluginEngine::getURL($this, array(), 'dozent/ects')));
                Navigation::addItem('/tools/modulkatalog', $navigation);
                break;

            case "root":
                $navigation = new Navigation($this->getPluginName(), PluginEngine::getURL($this, array(), 'dekanat/modul'));
                $navigation->addSubNavigation("modul", new Navigation("Modulkatalog erzeugen", PluginEngine::getURL($this, array(), 'dekanat/modul')));
                $navigation->addSubNavigation("ects", new Navigation("ECTS-Liste erzeugen", PluginEngine::getURL($this, array(), 'dekanat/ects')));
                $navigation->addSubNavigation("baum", new Navigation("Veranstaltungsbaum", PluginEngine::getURL($this, array(), 'root/baum')));
                Navigation::addItem('/tools/modulkatalog', $navigation);
                break;

            case "dekanat":
                $navigation = new Navigation($this->getPluginName(), PluginEngine::getURL($this, array(), 'dekanat/modul'));
                $navigation->addSubNavigation("modul", new Navigation("Modulkatalog erzeugen", PluginEngine::getURL($this, array(), 'dekanat/modul')));
                $navigation->addSubNavigation("ects", new Navigation("ECTS-Liste erzeugen", PluginEngine::getURL($this, array(), 'dekanat/ects')));
                Navigation::addItem('/tools/modulkatalog', $navigation);
                break;
        }
    }

    function getPluginName()
    {
        return "Modulkatalog-Generator";
    }
}