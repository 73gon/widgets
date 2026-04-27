<?php

namespace dashboard\MyWidgets\Simptrack;

use JobRouter\Api\Dashboard\v1\Widget;

class Simptrack extends Widget
	{

	public function getTitle()
		{
		return 'Simptrack';
		}

	public function getDimensions()
		{

		return [
			'minHeight' => 8,
			'minWidth' => 6,
			'maxHeight' => 15,
			'maxWidth' => 6,
		];
		}

	/*
	public function isAuthorized()
	{
		return $this->getUser()->isInJobFunction('Widgets');
	}
	*/
	public function isMandatory()
		{
		return true;
		}
	public function getData()
		{
		return [
			'currentUser' => $this->getUser()->getUsername(),
		];
		}
	}
