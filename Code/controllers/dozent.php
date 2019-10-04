<?php

class DozentController extends AuthenticatedController {

    /**
     * Aktionen und Einstellungen, werden vor jedem Seitenaufruf aufgerufen
     */
    public function before_filter(&$action, &$args)
    {
        $this->plugin = $this->dispatcher->plugin;
        $this->flash = Trails_Flash::instance();

        $this->set_layout(Request::isXhr() ? null : $GLOBALS['template_factory']->open('layouts/base'));

        $this->sidebar = Sidebar::Get();
        $this->sidebar->setImage('sidebar/file-sidebar.png');
    }


    /**
     * Wird aufgerufen, wenn man sich im 'ECTS-Liste erzeugen'-Menüpunkt befindet
     * Variablen für die Views werden festgelegt
     */
    public function ects_action()
    {
        Navigation::activateItem('/tools/modulkatalog/ects');

        $this->semesters = Semester::getAll();
        $this->current_semester = Semester::findCurrent();

        $this->user = User::findByUsername(get_username());
    }

}

