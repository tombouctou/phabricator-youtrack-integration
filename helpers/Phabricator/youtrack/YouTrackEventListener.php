<?php

/*
 * Copyright 2014 Pimpay
 */

class YouTrackEventListener extends PhutilEventListener
{
    private $message;
    public function register()
    {
        $this->listen(ArcanistEventType::TYPE_REVISION_WILLCREATEREVISION);
        $this->listen(ArcanistEventType::TYPE_DIFF_DIDBUILDMESSAGE);
    }

    public function handleEvent(PhutilEvent $event)
    {
        if ($event->getType() == ArcanistEventType::TYPE_DIFF_DIDBUILDMESSAGE)
        {
            $this->message = $event->getValue('message')->getRawCorpus();
            return;
        }
        if ($event->getType() != ArcanistEventType::TYPE_REVISION_WILLCREATEREVISION)
        {
            return;
        }
        $diff_id = $event->getValue('diffID');
        $spec = $event->getValue('specification');
        $revision_id = $spec['id'];

        /* Need to send a get request to youtrack to add commit. We pass the
         * diff id to youtrack via its api.
         */
        $workflow = $event->getValue('workflow');
        $youtrack_uri = $workflow->getConfigFromAnySource('youtrack.uri');
        
        if (!$youtrack_uri)
        {
            return;
        }

        $console = PhutilConsole::getConsole();

        $helper = new YouTrackBase([
            'uri' => $youtrack_uri,
            // login, password are filled automagically from .ytconfig
        ], $revision_id);

        $commit = new \stdClass();
        $commit->raw_author = $helper->getLogin();
        $commit->message = $this->message;
        
        if ($commit->raw_author)
        {
            $helper->preLogin();
            $helper->parseCommand($commit);
        }
    }
}
