<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Engine_chat extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('socket_server');
	}

	public function index()
	{
		$this->socket_server->server->run();
	}
}
