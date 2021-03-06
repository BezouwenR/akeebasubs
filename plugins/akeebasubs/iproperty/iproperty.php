<?php

/**
 * @package		akeebasubs
 * @copyright	Copyright (c)2010-2014 Nicholas K. Dionysopoulos / AkeebaBackup.com
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */
defined('_JEXEC') or die();

$akeebasubsinclude = include_once JPATH_ADMINISTRATOR . '/components/com_akeebasubs/assets/akeebasubs.php';
if (!$akeebasubsinclude)
{
	unset($akeebasubsinclude);
	return;
}
else
{
	unset($akeebasubsinclude);
}

class plgAkeebasubsIproperty extends plgAkeebasubsAbstract
{
	private $knownParamsKeys = array(
		'maxlistings', 'maxflistings', 'maxagents', 'maxfagents',  'maximgs',
	);

	public function __construct(& $subject, $config = array())
	{
		$templatePath = dirname(__FILE__);
		$name = 'iproperty';

		parent::__construct($subject, $name, $config, $templatePath);
	}

	/**
	 * Called whenever a subscription is modified. Namely, when its enabled status,
	 * payment status or valid from/to dates are changed.
	 */
	public function onAKSubscriptionChange($row, $info)
	{
		if (is_null($info['modified']) || empty($info['modified']))
			return;
		if (array_key_exists('enabled', (array) $info['modified']))
		{
			$this->onAKUserRefresh($row->user_id);
		}
	}

	/**
	 * Called whenever the administrator asks to refresh integration status.
	 *
	 * @param $user_id int The Joomla! user ID to refresh information for.
	 */
	public function onAKUserRefresh($user_id)
	{
		// Get all of the user's subscriptions
		$subscriptions = FOFModel::getTmpInstance('Subscriptions', 'AkeebasubsModel')
			->user_id($user_id)
			->getList();

		// Do I have to activate the user?
		$mustActivate	 = false;
		$mustDeactivate	 = false;
		$params			 = array();

		foreach ($subscriptions as $sub)
		{
			$level = $sub->akeebasubs_level_id;

			if ($sub->enabled)
			{
				if (array_key_exists($level, $this->addGroups))
				{
					$mustActivate	 = true;
					$params			 = $this->mixParams($params, $this->addGroups[$level]);
				}
			}
			else
			{
				if (array_key_exists($level, $this->addGroups))
				{
					$mustDeactivate = true;
				}
			}
		}

		if ($mustActivate && $mustDeactivate)
		{
			$mustDeactivate = false;
		}

		if ($mustActivate)
		{
			$this->publishAgent($user_id, $params);
		}
		elseif ($mustDeactivate)
		{
			$this->unpublishAgent($user_id);
		}
	}

	// =========================================================================
	// INTERNAL API SECTION
	// =========================================================================

	/**
	 * Publish an IP Agent linked with Joomla! user ID $user_id and limit
	 * parameters $params
	 *
	 * @param   integer  $user_id  The Joomla! user ID
	 * @param   array    $params   IP parameters for the agent
	 *
	 * @return  void
	 */
	private function publishAgent($user_id, $params)
	{
		// First, check if we already have agents for that user ID
		$db		 = JFactory::getDbo();
		$query	 = $db->getQuery(true)
			->select('*')
			->from($db->qn('#__iproperty_agents'))
			->where($db->qn('user_id') . ' = ' . $db->q($user_id));
		$db->setQuery($query);
		$agents	 = $db->loadObjectList();

		if (empty($agents))
		{
			// If we do not have any existing agents, create a new company and a new agent record
			// Load the user data
			$user = FOFModel::getTmpInstance('Users', 'AkeebasubsModel')
				->user_id($user_id)
				->getMergedData($user_id);

			// Create a company
			$name = empty($user->businessname) ? $user->name : $user->businessname;

			require_once JPATH_ADMINISTRATOR . '/components/com_akeebasubs/helpers/filter.php';
			$alias = AkeebasubsHelperFilter::toSlug($name);

			$company = (object) array(
					'name'			 => $name,
					'alias'			 => $alias,
					'description'	 => '&nbsp;',
					'street'		 => $user->address1,
					'city'			 => $user->city,
					'locstate'		 => $user->state,
					'province'		 => '',
					'postcode'		 => $user->zip,
					'country'		 => $user->country,
					'fax'			 => '',
					'phone'			 => '',
					'email'			 => $user->email,
					'website'		 => '',
					'featured'		 => 0,
					'icon'			 => 'nopic.png',
					'clicense'		 => '',
					'language'		 => '',
					'state'			 => 1,
					'params'		 => json_encode($params)
			);

			$db->insertObject('#__iproperty_companies', $company);

			$companyid = $db->insertid();

			// Create an agent
			$nameParts	 = explode(' ', $user->name, 2);
			$firstName	 = $nameParts[0];
			if (count($nameParts) > 1)
			{
				$lastName = $nameParts[1];
			}
			else
			{
				$lastName = '';
			}
			$alias = AkeebasubsHelperFilter::toSlug($user->name);

			$agent = (object) array(
					'agent_type' => 1,
					'hometeam'	 => 0,
					'fname'		 => $firstName,
					'lname'		 => $lastName,
					'alias'		 => $alias,
					'company'	 => $companyid,
					'email'		 => $user->email,
					'phone'		 => '',
					'mobile'	 => '',
					'fax'		 => '',
					'street'	 => $user->address1,
					'street2'	 => $user->address2,
					'city'		 => $user->city,
					'locstate'	 => $user->state,
					'province'	 => '',
					'postcode'	 => $user->zip,
					'country'	 => $user->country,
					'website'	 => '',
					'bio'		 => '&nbsp;',
					'user_id'	 => $user_id,
					'featured'	 => 0,
					'icon'		 => 'nopic.png',
					'msn'		 => '',
					'skype'		 => '',
					'gtalk'		 => '',
					'linkedin'	 => '',
					'facebook'	 => '',
					'twitter'	 => '',
					'social1'	 => '',
					'alicense'	 => '',
					'state'		 => 1,
					'params'	 => '',
			);
			$db->insertObject('#__iproperty_agents', $agent);
		}
		else
		{
			// If we have existing agents, we need to do two things:
			// a. Make sure all agent records are enabled
			$query = $db->getQuery(true)
				->update($db->qn('#__iproperty_agents'))
				->set($db->qn('state') . ' = ' . $db->q(1))
				->where($db->qn('user_id') . ' = ' . $db->q($user_id));
			$db->setQuery($query);
			$db->execute();

			// b. Update the company parameters
			$company_ids_raw = array();
			foreach ($agents as $agent)
			{
				$company_ids_raw[] = $agent->company;
			}
			$company_ids_raw = array_unique($company_ids_raw);
			$company_ids	 = array();
			foreach ($company_ids_raw as $cid)
			{
				$company_ids[] = $db->q($cid);
			}

			$query		 = $db->getQuery(true)
				->select('*')
				->from($db->qn('#__iproperty_companies'))
				->where($db->qn('id') . ' IN (' . implode(',', $company_ids) . ')');
			$db->setQuery($query);
			$companies	 = $db->loadObjectList();

			foreach ($companies as $company)
			{
				$cparams		 = json_decode($company->params, true);
				$cparams		 = $this->mixParams($cparams, $params);
				$company->params = json_encode($cparams);
				$company->state	 = 1;
				$result			 = $db->updateObject('#__iproperty_companies', $company, 'id');
			}
		}
	}

	/**
	 * Remove the IP Agent and Company attached to the Joomla! user ID $user_id
	 *
	 * @param   integer  $user_id  The Joomla! user ID
	 *
	 * @return  void
	 */
	private function unpublishAgent($user_id)
	{
		// First, check if we already have agents for that user ID
		$db		 = JFactory::getDbo();
		$query	 = $db->getQuery(true)
			->select('*')
			->from($db->qn('#__iproperty_agents'))
			->where($db->qn('user_id') . ' = ' . $db->q($user_id));
		$db->setQuery($query);
		$agents	 = $db->loadObjectList();

		if (empty($agents))
			return;

		// Unpublish agent records
		$db		 = JFactory::getDbo();
		$query	 = $db->getQuery(true)
			->update($db->qn('#__iproperty_agents'))
			->set($db->qn('state') . ' = ' . $db->q(0))
			->where($db->qn('user_id') . ' = ' . $db->q($user_id));
		$db->setQuery($query);
		$db->execute();

		// b. Unpublish the companies of the user
		$company_ids_raw = array();
		foreach ($agents as $agent)
		{
			$company_ids_raw[] = $agent->company;
		}
		$company_ids_raw = array_unique($company_ids_raw);
		$company_ids	 = array();
		foreach ($company_ids_raw as $cid)
		{
			$company_ids[] = $db->q($cid);
		}

		$query = $db->getQuery(true)
			->update($db->qn('#__iproperty_companies'))
			->set($db->qn('state') . ' = ' . $db->q('0'))
			->where($db->qn('id') . ' IN (' . implode(',', $company_ids) . ')');
		$db->setQuery($query);
		$db->execute();
	}

	/**
	 * Mix two parameter sets, keeping the maximum value between the two
	 *
	 * @param   array  $old  Old parameter set
	 * @param   array  $new  New parameter set
	 *
	 * @return  array  The mixed parameter set
	 */
	private function mixParams($old, $new)
	{
		$oldKeys = count($old) ? array_keys($old) : array();
		$newKeys = count($new) ? array_keys($new) : array();
		$allKeys = array_merge($oldKeys, $newKeys);

		if (empty($allKeys))
			return array();
		$ret = array();

		foreach ($allKeys as $key)
		{
			if (array_key_exists($key, $old) && !array_key_exists($key, $new))
			{
				$ret[$key] = $old[$key];
			}
			elseif (!array_key_exists($key, $old) && array_key_exists($key, $new))
			{
				$ret[$key] = $new[$key];
			}
			else
			{
				$ret[$key] = max($old[$key], $new[$key]);
			}
		}

		return $ret;
	}

	/**
	 * Not used. It's here because it's declared as abstract in the base class.
	 *
	 * @return  array
	 */
	protected function getGroups()
	{
		return array();
	}

	protected function loadGroupAssignments()
	{
		$this->addGroups	 = array();
		$this->removeGroups	 = array();

		$model	 = FOFModel::getTmpInstance('Levels', 'AkeebasubsModel');
		$levels	 = $model->getList(true);

		if (!empty($levels))
		{
			foreach ($levels as $level)
			{
				if (is_string($level->params))
				{
					$level->params = @json_decode($level->params);
					if (empty($level->params))
					{
						$level->params = new stdClass();
					}
				}
				elseif (empty($level->params))
				{
					continue;
				}

				$levelparams = array();

				foreach ($this->knownParamsKeys as $k)
				{
					$paramsKey = strtolower($this->name) . '_' . $k;

					if (property_exists($level->params, $paramsKey))
					{
						$levelparams[$k] = $level->params->$paramsKey;
					}
				}

				if (!empty($levelparams))
				{
					$this->addGroups[$level->akeebasubs_level_id] = $levelparams;
				}
			}
		}
	}

	public function getParamValue($level_id, $paramKey, $default = 0)
	{
		if (array_key_exists($level_id, $this->addGroups))
		{
			if (!is_array($this->addGroups[$level_id]))
			{
				return $default;
			}

			if (array_key_exists($paramKey, $this->addGroups[$level_id]))
			{
				return (int)$this->addGroups[$level_id][$paramKey];
			}
			else
			{
				return $default;
			}
		}
		else
		{
			return $default;
		}
	}

	// =========================================================================
	// LEGACY SECTION
	// =========================================================================

	/**
	 * Parse legacy options format
	 *
	 * @param   array  $rawData  Legacy data to parse
	 *
	 * @return  array  The parsed configuration
	 */
	protected function parseGroups($rawData)
	{
		$ret = array();

		if (empty($rawData))
			return $ret;

		// Just in case something funky happened...
		$rawData = str_replace("\\n", "\n", $rawData);
		$rawData = str_replace("\r", "\n", $rawData);
		$rawData = str_replace("\n\n", "\n", $rawData);

		$lines = explode("\n", $rawData);

		foreach ($lines as $line)
		{
			$line	 = trim($line);
			$parts	 = explode('=', $line, 2);
			if (count($parts) != 2)
				continue;

			$level		 = $parts[0];
			$rawParams	 = $parts[1];

			$levelId = $this->ASLevelToId($level);

			$params		 = explode(',', $rawParams);
			$paramsArray = array();
			if (empty($params))
			{
				$ret[$levelId] = array();
			}
			else
			{
				foreach ($params as $paramString)
				{
					$paramParts			 = explode('=', $paramString, 2);
					if (count($paramParts) != 2)
						continue;
					$key				 = trim($paramParts[0]);
					$value				 = intval(trim($paramParts[1]));
					$paramsArray[$key]	 = $value;
				}
				$ret[$levelId] = $paramsArray;
			}
		}

		return $ret;
	}

	/**
	 * Moves this plugin's settings from the plugin into each subscription
	 * level's configuration parameters.
	 */
	protected function upgradeSettings($config = array())
	{
		$model			 = FOFModel::getTmpInstance('Levels', 'AkeebasubsModel');
		$levels			 = $model->getList(true);
		$addgroupsKey	 = strtolower($this->name) . '_addgroups';
		$removegroupsKey = strtolower($this->name) . '_removegroups';

		if (!empty($levels))
		{
			foreach ($levels as $level)
			{
				$save = false;

				if (is_string($level->params))
				{
					$level->params = @json_decode($level->params);

					if (empty($level->params))
					{
						$level->params = new stdClass();
					}
				}
				elseif (empty($level->params))
				{
					$level->params = new stdClass();
				}

				if (array_key_exists($level->akeebasubs_level_id, $this->addGroups))
				{
					$data = $this->addGroups[$level->akeebasubs_level_id];
					if (!empty($data))
					{
						foreach ($data as $k => $v)
						{
							$key = strtolower($this->name) . '_' . $k;

							if (empty($level->params->$key))
							{
								$level->params->$key = $v;
								$save				 = true;
							}
						}
					}
				}

				if ($save)
				{
					$level->params	 = json_encode($level->params);
					$result			 = $model->setId($level->akeebasubs_level_id)->save($level);
				}
			}
		}

		// Remove the plugin parameters
		if (isset($config['params']))
		{
			$configParams	 = @json_decode($config['params']);
			unset($configParams->addgroups);
			unset($configParams->removegroups);
			$param_string	 = @json_encode($configParams);

			$db		 = JFactory::getDbo();
			$query	 = $db->getQuery(true)
				->update($db->qn('#__extensions'))
				->where($db->qn('type') . '=' . $db->q('plugin'))
				->where($db->qn('element') . '=' . $db->q(strtolower($this->name)))
				->where($db->qn('folder') . '=' . $db->q('akeebasubs'))
				->set($db->qn('params') . ' = ' . $db->q($param_string));
			$db->setQuery($query);
			$db->execute();
		}
	}

}