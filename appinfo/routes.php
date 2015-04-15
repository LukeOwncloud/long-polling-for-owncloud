<?php
use OCA\Long_Polling\PollController;

/**
 *
 * @author Luke Owncloud
 */
$this->create('longpoll', '/poll')
    ->method('GET')
    ->action(function  ()
{
    $c = new PollController();
    $c->longPoll();
});
