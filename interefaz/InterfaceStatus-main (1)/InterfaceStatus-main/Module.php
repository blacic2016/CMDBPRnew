<?php

namespace Modules\InterfaceStatus;

use Zabbix\Core\CModule,
    APP,
    CMenu,
    CMenuItem;

class Module extends CModule {
    public function init(): void {
        APP::Component()->get('menu.main')
            ->findOrAdd(_('Monitoring'))
            ->getSubmenu()
            ->insertAfter(_('Maps'),
                (new CMenuItem(_('Interface Status')))
                    ->setAction('interfacestatus.view')
            );
    }
} 