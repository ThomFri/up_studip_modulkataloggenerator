<?php

class RootController extends AuthenticatedController {

    /**
     * Aktionen und Einstellungen, werden vor jedem Seitenaufruf aufgerufen
     */
    public function before_filter(&$action, &$args)
    {
        $this->plugin = $this->dispatcher->plugin;
        $this->flash = Trails_Flash::instance();

        $this->set_layout(Request::isXhr() ? null : $GLOBALS['template_factory']->open('layouts/base'));

        $this->sidebar = Sidebar::Get();
        $this->sidebar->setImage('sidebar/search-sidebar.png');
    }

    /**
     * Wird aufgerufen, wenn man sich im 'Veranstaltungsbaum'-Menüpunkt befindet
     * Variablen für die Views werden festgelegt
     */
    public function baum_action(){
        Navigation::activateItem('tools/modulkatalog/baum');

        $wiwi = Institute::findOneByName('Wirtschaftswissenschaftliche Fakultät');
        $this->wiwiTree = StudipStudyArea::findOnebyStudip_object_id($wiwi->id);
    }

}

