<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class boilerThermostat extends eqLogic
{
  /*     * *************************Attributs****************************** */
  /*     * ***********************Methode static*************************** */
  public static function createEquipment($type, $name)
  {
    log::add('boilerThermostat', 'info', 'création equipement ' . $name . ' de type ' . $type);
    $eqManager = new boilerThermostat();
    $eqManager->setEqType_name('boilerThermostat');
    $eqManager->setName($name);
    $eqManager->setConfiguration('type', $type);
    $eqManager->save();
  }

  public static function runActuatorLogic($param)
  {
    try {
      $processKey = uniqid("runActuatorLogic");
      log::add('boilerThermostat', 'debug', 'on runActuatorLogic ' . $processKey);
      log::add('boilerThermostat', 'debug', $processKey . ' : ' . json_encode($param));

      //Récupération de l'equipement correspondant
      $eqp = eqLogic::byId($param['eqpID']);

      //Récupération de l'actionneur correspondant
      $actuators = $eqp->getConfiguration('childActuators');
      $validActuator = null;
      foreach ($actuators as $actuator) {
        if ($actuator['type'] != 0) continue;
        $actuatorCmd = cmd::byId(str_replace('#', '', $actuator['cmd']));
        $actuatorCmdValue = $actuatorCmd->getValue();
        //log::add('boilerThermostat', 'debug', 'value lié de  : ' . str_replace('#', '', $actuatorCmdValue) . ' vs ' . $param['event_id']);
        if (str_replace('#', '', $actuatorCmdValue) != $param['event_id']) continue;
        $validActuator = $actuator;
        break;
      }

      if ($validActuator == null) throw new Exception($processKey . ' : ' . 'Action introuvable avec ID=' . $param['event_id']);

      //On vérifie si l'evenement est valide
      $actuatorCmdInfo = cmd::byId($param['event_id']);
      if (!is_object($actuatorCmdInfo))
        throw new Exception($processKey . ' : ' . "Impossible de trouver l'emetteur de l'évènement");
      $collectDate = $actuatorCmdInfo->getCache('collectDate');
      $valueDate = $actuatorCmdInfo->getCache('valueDate');
      if ($validActuator['ignoreFirstEvent'] == 1) {
        $actuatorsStatus = $eqp->getConfiguration('actuatorsStatus');
        log::add('boilerThermostat', 'debug', $processKey . ' : ' . ' Ancien status : ' . $actuatorsStatus[$param['event_id']]);
        if ($collectDate != $valueDate && $actuatorsStatus[$param['event_id']] == 1) {
          log::add('boilerThermostat', 'debug', $processKey . ' : ' . ' Evenement valide, collectDate : ' . $collectDate  . ', valueDate : ' . $valueDate);
          $actuatorsStatus[$param['event_id']] = 0;
          $actuatorsStatus = $eqp->setConfiguration('actuatorsStatus', $actuatorsStatus);
          $eqp->save();
        } else if ($collectDate == $valueDate && $actuatorsStatus[$param['event_id']] != 1) {
          log::add('boilerThermostat', 'debug', $processKey . ' : ' . ' Evenement non valide, collectDate : ' . $collectDate  . ', valueDate : ' . $valueDate);
          $actuatorsStatus[$param['event_id']] = 1;
          $actuatorsStatus = $eqp->setConfiguration('actuatorsStatus', $actuatorsStatus);
          $eqp->save();
          return;
        } else {
          log::add('boilerThermostat', 'debug', $processKey . ' : ' . ' Evenement non valide sans incidence sur le statut, collectDate : ' . $collectDate  . ', valueDate : ' . $valueDate);
          return;
        }
      } else {
        if ($collectDate == $valueDate)
          log::add('boilerThermostat', 'debug', $processKey . ' : ' . ' Evenement valide, collectDate : ' . $collectDate  . ', valueDate : ' . $valueDate);
        else {
          log::add('boilerThermostat', 'debug', $processKey . ' : ' . ' Evenement non valide, collectDate : ' . $collectDate  . ', valueDate : ' . $valueDate);
          return;
        }
      }
      log::add('boilerThermostat', 'debug', $processKey . ' : ' . ' Hysteresis : ' . $eqp->getConfiguration('hysteresis')  . ', offset : ' . $validActuator['offset']);

      //Calcul de la consigne lié
      $newSetPoint = $param['value'] - $validActuator['offset'];
      $newSetPoint = $newSetPoint - $eqp->getConfiguration('hysteresis');
      $newSetPoint = round($newSetPoint * 2 - 0.49, 0) / 2;
      //Calcul du delta consigne vs consigne ajustée
      $cmdSetPoint = $eqp->getCmd('info', 'setPoint');
      $cmdAdjustedSetPoint = $eqp->getCmd('info', 'adjustedSetPoint');
      $adjustedSetpointValue = $cmdAdjustedSetPoint->execCmd();

      //On controle que la consigne est bien supérieur à la valeur min
      if ($newSetPoint < $cmdAdjustedSetPoint->getConfiguration('minValue', $newSetPoint)) {
        log::add('boilerThermostat', 'info', $processKey . ' : ' . 'Le retour de consigne est inférieur à la valeur min de consigne : ' . $newSetPoint);
        return;
      }
      if ($newSetPoint == $adjustedSetpointValue) {
        log::add('boilerThermostat', 'debug', $processKey . ' : ' . 'New value already equal to adjusted value : ' . $newSetPoint);
        return;
      }

      log::add('boilerThermostat', 'debug', $processKey . ' : ' . 'Consigne ajustée actuel : ' . $adjustedSetpointValue . ', nouvelle consigne ajustée : ' . $newSetPoint);
      $delta = $cmdSetPoint->execCmd() - $adjustedSetpointValue;
      $newSetPoint = $newSetPoint + $delta;
      $cmdSetPointActuator = $eqp->getCmd('action', 'setPointActuator');
      log::add('boilerThermostat', 'info', $processKey . ' : ' . 'Définition de la consigne : ' . $newSetPoint . ' par la vanne ' . $param['event_id']);
      scenarioExpression::createAndExec('action', $cmdSetPointActuator->getId(), array('slider' => $newSetPoint, 'mode' => 'Manuel Vanne'));
    } catch (Exception $e) {
      log::add('boilerThermostat', 'error', displayExeption($e) . ', errCode : ' . $e->getCode());
      throw $e;
    }
  }

  public static function runBoilerLogic($param)
  {
    //$param['event_id'];
    //$param['value'];
    //$param['eqp_id'];
    try {
      $processKey = uniqid("runBoilerLogic");
      log::add('boilerThermostat', 'debug', 'on runBoilerLogic ' . $processKey);
      $globalStatus = 0;
      //On vérifie si le chauffage est nécessaire
      foreach (self::byTypeAndSearhConfiguration('boilerThermostat', 'Thermostat') as $eqp) {
        if (!$eqp->getIsEnable()) continue;
        if ($eqp->getCmd(null, 'status')->execCmd() == 1) {
          $globalStatus = 1;
        }
      }

      if ($globalStatus) {
        $switchOn = cmd::byId(str_replace('#', '', config::byKey('onHeating', 'boilerThermostat')));
        log::add('boilerThermostat', 'debug', $processKey . ' : ' . 'Le switchOn est ' . $switchOn->getHumanName());
        $switchState = $switchOn->getCmdValue();
        if (is_object($switchState)) log::add('boilerThermostat', 'debug', "L'info du switch est " . $switchState->getHumanName());
        if (is_object($switchState) && $switchState->execCmd()) {
          log::add('boilerThermostat', 'info', $processKey . ' : ' . 'Le switch ' . $switchOn->getHumanName() . ' est déjà actif (' . $switchState->execCmd() . ')');
          return;
        } else $switchOn->execCmd();
        log::add('boilerThermostat', 'info', $processKey . ' : ' . 'Activation du switch ' . $switchOn->getHumanName());
      } else {
        $switchOff = cmd::byId(str_replace('#', '', config::byKey('offHeating', 'boilerThermostat')));
        log::add('boilerThermostat', 'debug', $processKey . ' : ' . 'Le switchOff est ' . $switchOff->getHumanName());
        $switchState = $switchOff->getCmdValue();
        if (is_object($switchState)) log::add('boilerThermostat', 'debug', $processKey . ' : ' . "L'info du switch est " . $switchState->getHumanName());
        if (is_object($switchState) && !$switchState->execCmd()) {
          log::add('boilerThermostat', 'info', $processKey . ' : ' . 'Le switch ' . $switchOff->getHumanName() . ' est déjà actif (' . $switchState->execCmd() . ')');
          return;
        } else $switchOff->execCmd();
        log::add('boilerThermostat', 'info', $processKey . ' : ' . 'Activation du switch ' . $switchOff->getHumanName());
      }
    } catch (Exception $e) {
      log::add('boilerThermostat', 'error', displayExeption($e) . ', errCode : ' . $e->getCode());
      throw $e;
    }
  }

  /*     * *********************Méthodes d'instance************************* */
  private function addManagerCmds()
  {
    try {
      $newCMD = false;
      //actionneur Off
      $cmd = $this->getCmd(null, 'Off');
      if (!is_object($cmd)) {
        $cmd = new boilerThermostatCmd();
        $cmd->setName(__('Off', __FILE__));
        $cmd->setLogicalId('Off');
        $cmd->setEqLogic_id($this->getId());
        $cmd->setType('action');
        $cmd->setSubType('other');
        $cmd->setOrder(0);
        $cmd->save();
        $newCMD = true;
      }

      //actionneur On
      $cmd = $this->getCmd(null, 'On');
      if (!is_object($cmd)) {
        $cmd = new boilerThermostatCmd();
        $cmd->setName(__('On', __FILE__));
        $cmd->setLogicalId('On');
        $cmd->setEqLogic_id($this->getId());
        $cmd->setType('action');
        $cmd->setSubType('other');
        $cmd->setOrder(1);
        $cmd->save();
        $newCMD = true;
      }

      //Etat
      $cmd = $this->getCmd(null, 'state');
      if (!is_object($cmd)) {
        $cmd = new boilerThermostatCmd();
        $cmd->setName(__('Etat', __FILE__));
        $cmd->setLogicalId('state');
        $cmd->setEqLogic_id($this->getId());
        $cmd->setType('info');
        $cmd->setSubType('binary');
        $cmd->setOrder(2);
        $cmd->setConfiguration('value', 1);
        $cmd->save();
        $cmd->event(1);
        $newCMD = true;
      }

      //pourcentAdjustment
      $cmd = $this->getCmd(null, 'pourcentAdjustment');
      if (!is_object($cmd)) {
        $cmd = new boilerThermostatCmd();
        $cmd->setName(__('Pourcentage ajustement', __FILE__));
        $cmd->setLogicalId('pourcentAdjustment');
        $cmd->setEqLogic_id($this->getId());
        $cmd->setConfiguration('minValue', '0');
        $cmd->setConfiguration('maxValue', '100');
        $cmd->setConfiguration('minValueReplace', 1);
        $cmd->setConfiguration('maxValueReplace', 1);
        $cmd->setUnite('%');
        $cmd->setType('info');
        $cmd->setSubType('numeric');
        $cmd->setConfiguration('calcul', '0');
        $cmd->setOrder(3);
        $cmd->save();
        $cmd->event(0);
      } else if ($newCMD) {
        $cmd->setOrder(3);
        $cmd->save();
      }

      //Info presenceMode
      $cmd = $this->getCmd(null, 'presenceMode');
      if (!is_object($cmd)) {
        $cmd = new boilerThermostatCmd();
        $cmd->setName(__('Mode de présence', __FILE__));
        $cmd->setLogicalId('presenceMode');
        $cmd->setEqLogic_id($this->getId());
        $cmd->setType('info');
        $cmd->setSubType('string');
        $cmd->setConfiguration('mode', 'Auto');
        $cmd->setOrder(4);
        $cmd->save();
        $cmd->event('Auto');
      } else if ($newCMD) {
        $cmd->setOrder(4);
        $cmd->save();
      }

      //actionneur PresenceAuto
      $cmd = $this->getCmd(null, 'presenceAuto');
      if (!is_object($cmd)) {
        $cmd = new boilerThermostatCmd();
        $cmd->setName(__('Auto', __FILE__));
        $cmd->setLogicalId('presenceAuto');
        $cmd->setEqLogic_id($this->getId());
        $cmd->setType('action');
        $cmd->setSubType('other');
        $cmd->setConfiguration('mode', 'Auto');
        $cmd->setOrder(5);
        $cmd->save();
      } else if ($newCMD) {
        $cmd->setOrder(5);
        $cmd->save();
      }

      //actionneur PresenceIn
      $cmd = $this->getCmd(null, 'presenceIn');
      if (!is_object($cmd)) {
        $cmd = new boilerThermostatCmd();
        $cmd->setName(__('Présent', __FILE__));
        $cmd->setLogicalId('presenceIn');
        $cmd->setEqLogic_id($this->getId());
        $cmd->setType('action');
        $cmd->setSubType('other');
        $cmd->setConfiguration('mode', 'Présent');
        $cmd->setOrder(6);
        $cmd->save();
      } else if ($newCMD) {
        $cmd->setOrder(6);
        $cmd->save();
      }

      //actionneur PresenceOut
      $cmd = $this->getCmd(null, 'presenceOut');
      if (!is_object($cmd)) {
        $cmd = new boilerThermostatCmd();
        $cmd->setName(__('Absent', __FILE__));
        $cmd->setLogicalId('presenceOut');
        $cmd->setEqLogic_id($this->getId());
        $cmd->setType('action');
        $cmd->setSubType('other');
        $cmd->setConfiguration('mode', 'Absent');
        $cmd->setOrder(7);
        $cmd->save();
      } else if ($newCMD) {
        $cmd->setOrder(7);
        $cmd->save();
      }
    } catch (Exception $e) {
      log::add('boilerThermostat', 'error', displayExeption($e) . ', errCode : ' . $e->getCode());
      throw $e;
    }
  }

  private function addThermostatCmds()
  {
    try {
      //Get the manager adjustment cmd
      $managerAdjustment = $this->getManager()->getCmd(null, 'pourcentAdjustment');

      //Info Mode
      $cmd = $this->getCmd('info', 'mode');
      if (!is_object($cmd)) {
        $cmd = new boilerThermostatCmd();
        $cmd->setName(__('Mode', __FILE__));
        $cmd->setLogicalId('mode');
        $cmd->setEqLogic_id($this->getId());
        $cmd->setType('info');
        $cmd->setSubType('string');
        $cmd->setIsVisible(1);
        $cmd->setOrder(0);
        $cmd->save();
        $cmd->event(__('Manuel', __FILE__));
      }

      //Temperature sensor associated
      $cmd = $this->getCmd(null, 'associatedTemperatureSensor');
      if (!is_object($cmd)) {
        $cmd = new boilerThermostatCmd();
        $cmd->setName(__('Température', __FILE__));
        $cmd->setLogicalId('associatedTemperatureSensor');
        $cmd->setEqLogic_id($this->getId());
        $cmd->setUnite('°C');
        $cmd->setType('info');
        $cmd->setSubType('numeric');
        $cmd->setTemplate('dashboard', 'badge');
        $cmd->setTemplate('mobile', 'badge');
        $cmd->setIsVisible(0);
        $cmd->setOrder(3);
        $cmd->save();
        $cmd->event(19);
      }
      $associatedSensor = $cmd->getId();

      //SetPoint
      $cmd = $this->getCmd(null, 'setPoint');
      if (!is_object($cmd)) {
        $cmd = new boilerThermostatCmd();
        $cmd->setName(__('Consigne', __FILE__));
        $cmd->setLogicalId('setPoint');
        $cmd->setEqLogic_id($this->getId());
        $cmd->setConfiguration('minValue', '10');
        $cmd->setConfiguration('maxValue', '25');
        $cmd->setConfiguration('minValueReplace', 1);
        $cmd->setConfiguration('maxValueReplace', 1);
        $cmd->setUnite('°C');
        $cmd->setType('info');
        $cmd->setSubType('numeric');
        $cmd->setTemplate('dashboard', 'badge');
        $cmd->setTemplate('mobile', 'badge');
        $cmd->setIsVisible(0);
        $cmd->setOrder(4);
        $cmd->save();
      }
      $setPointID = $cmd->getId();

      //Adjusted SetPoint
      $cmd = $this->getCmd(null, 'adjustedSetPoint');
      if (!is_object($cmd)) {
        $cmd = new boilerThermostatCmd();
        $cmd->setName(__('Consigne ajustée', __FILE__));
        $cmd->setLogicalId('adjustedSetPoint');
        $cmd->setEqLogic_id($this->getId());
        $cmd->setConfiguration('minValue', '10');
        $cmd->setConfiguration('maxValue', '25');
        $cmd->setConfiguration('minValueReplace', 1);
        $cmd->setConfiguration('maxValueReplace', 1);
        $cmd->setUnite('°C');
        $cmd->setType('info');
        $cmd->setSubType('numeric');
        $cmd->setConfiguration('calcul', '#' . $setPointID . '#-#' . $managerAdjustment->getId() . '#*0.03');
        $cmd->setTemplate('dashboard', 'Round');
        $cmd->setTemplate('mobile', 'Round');
        $cmd->setOrder(5);
        $cmd->save();
      }
      $adjustedSetPointID = $cmd->getId();

      //actionneur SetPoint
      $cmd = $this->getCmd(null, 'setPointActuator');
      if (!is_object($cmd)) {
        $cmd = new boilerThermostatCmd();
        $cmd->setName(__('Actionneur consigne', __FILE__));
        $cmd->setLogicalId('setPointActuator');
        $cmd->setEqLogic_id($this->getId());
        $cmd->setConfiguration('minValue', '10');
        $cmd->setConfiguration('maxValue', '25');
        $cmd->setUnite('°C');
        $cmd->setType('action');
        $cmd->setSubType('slider');
        $cmd->setTemplate('dashboard', 'button');
        $cmd->setTemplate('mobile', 'thermostat');
        $cmd->setConfiguration('lastCmdValue', 19);
        $cmd->setOrder(1);
        $cmd->save();
        $opt = array();
        $opt['slider'] = 19;
        $cmd->execCmd($opt);
      }

      //Etat
      $cmd = $this->getCmd(null, 'status');
      if (!is_object($cmd)) {
        $cmd = new boilerThermostatCmd();
        $cmd->setName(__('Etat', __FILE__));
        $cmd->setLogicalId('status');
        $cmd->setEqLogic_id($this->getId());
        $cmd->setUnite('');
        $cmd->setType('info');
        $cmd->setSubType('binary');
        $cmd->setIsVisible(0);
        $cmd->setOrder(2);
        $cmd->save();
        $cmd->event(0);
      }

      //Etat (humain)
      $cmd = $this->getCmd(null, 'statusName');
      if (!is_object($cmd)) {
        $cmd = new boilerThermostatCmd();
        $cmd->setName(__('Status', __FILE__));
        $cmd->setLogicalId('statusName');
        $cmd->setEqLogic_id($this->getId());
        $cmd->setType('info');
        $cmd->setSubType('string');
        $cmd->setIsVisible(0);
        $cmd->setOrder(2);
        $cmd->save();
        $cmd->event(__('Arrêté', __FILE__));
      }

      //Gestion des modes
      $this->modeManagement();
    } catch (Exception $e) {
      log::add('boilerThermostat', 'error', displayExeption($e) . ', errCode : ' . $e->getCode());
      throw $e;
    }
  }

  public function copy($_name)
  {
    //On copy l'eqp mais pas les commandes, celle-ci sont recréer automatiquement
    $eqLogicCopy = clone $this;
    $eqLogicCopy->setName($_name);
    $eqLogicCopy->setId('');
    $eqLogicCopy->save();
    $eqLogicCopy->manageCmdOrder();
    return $eqLogicCopy;
  }

  public function getOrderedModeList()
  {
    $modes = $this->getConfiguration('modes');
    $modeList = array();
    $modeID = 0;
    $modeList['Off'] = array('option' => 'Off', 'name' => 'Off', 'ID' => $modeID++);
    if (is_array($modes)) {
      foreach ($modes as $mode) {
        $mode['ID'] = $modeID++;
        $modeList[$mode['name']] = $mode;
      }
    }
    return $modeList;
  }

  public function modeManagement()
  {
    $modeList = $this->getOrderedModeList();

    //Ajout / suppression des modes
    foreach ($this->getCmd('action', 'modeAction', null, true) as $cmd) {
      if (isset($modeList[$cmd->getName()])) {
        if ($cmd->getConfiguration('option') != $modeList[$cmd->getName()]['option']) {
          log::add('boilerThermostat', 'debug', 'Mise a jour de la commande : ' . $cmd->getName());
          $cmd->setConfiguration('option', $modeList[$cmd->getName()]['option']);
          $cmd->save();
        }
        unset($modeList[$cmd->getName()]);
      } else {
        log::add('boilerThermostat', 'debug', 'Suppression de la commande : ' . $cmd->getName());
        $cmd->remove();
      }
    }

    foreach ($modeList as $mode) {
      log::add('boilerThermostat', 'debug', 'création de la commande' . $mode['name']);
      $cmd = new boilerThermostatCmd();
      $cmd->setName(__($mode['name'], __FILE__));
      $cmd->setLogicalId('modeAction');
      $cmd->setEqLogic_id($this->getId());
      $cmd->setType('action');
      $cmd->setSubType('other');
      $cmd->setConfiguration('option', $mode['option']);
      $cmd->setOrder(0);
      $cmd->setIsVisible(1);
      $cmd->save();
    }
  }

  public function manageCmdOrder()
  {
    //Récupération du "order de l'info mode"
    //log::add('boilerThermostat','debug','In ManageCmdOrder');
    $infoModeOrder = cmd::byID($this->getCmd('info', 'mode')->getId())->getOrder();
    //log::add('boilerThermostat','debug','Order of Mode Info = '.$infoModeOrder );
    $modeList = $this->getOrderedModeList();

    //Gestion de l'ordre des commandes
    foreach (cmd::byEqLogicID($this->getId()) as $cmd) {
      //log::add('boilerThermostat','debug','cmd '.$cmd->getName().' ordrer = ' . $cmd->getOrder());
      if ($cmd->getLogicalId() == 'modeAction') {
        $cmd->setOrder($infoModeOrder + $modeList[$cmd->getName()]['ID'] + 1);
        $cmd->save();
      } else if ($cmd->getLogicalId() != 'modeAction' && $cmd->getOrder() > $infoModeOrder) {
        $cmd->setOrder($cmd->getOrder() + count($modeList));
        $cmd->save();
      }
      //log::add('boilerThermostat','debug','cmd '.$cmd->getName().' ordrer = ' . $cmd->getOrder());
    }
  }

  public function getManager()
  {
    try {
      return self::byTypeAndSearhConfiguration('boilerThermostat', 'Manager')[0];
    } catch (Exception $e) {
      log::add('boilerThermostat', 'error', displayExeption($e) . ', errCode : ' . $e->getCode());
      throw $e;
    }
  }

  public function getManagerState()
  {
    return $this->getManager()->getCmd(null, 'state')->getConfiguration('value');
  }

  public function getChildSetPointValue()
  {
    $adjustedSetPointCmd = $this->getCmd(null, 'adjustedSetPoint');
    if (!is_object($adjustedSetPointCmd)) return 0;
    return $adjustedSetPointCmd->getConfiguration('childSetpoint', 0);
  }

  public function setChildSetPointValue($newValue)
  {
    $adjustedSetPointCmd = $this->getCmd(null, 'adjustedSetPoint');
    if (!is_object($adjustedSetPointCmd)) {
      //Manage error
      return;
    }
    $adjustedSetPointCmd->setConfiguration('childSetpoint', $newValue);
    $adjustedSetPointCmd->save();
  }

  public function processChildActuators($type, $setPoint = null)
  {
    //Si option est défini, on calcul la valeur a utilisé et on vérifie si il y a besoin de changer
    $opts = array();
    if ($type == 0 && isset($setPoint)) {
      $opts['slider'] = round(($setPoint + $this->getConfiguration('hysteresis')) * 2 + 0.49, 0) / 2;
      if ($opts['slider'] != $this->getChildSetPointValue()) {
        $this->setChildSetPointValue($opts['slider']);
      }
      //Si pas besoin de mise a jour on quitte pour ne pas faire travailler les vannes pour rien. A commenter si inutile??
      else return;
    }

    $actuators = $this->getConfiguration('childActuators');
    if (is_array($actuators)) {
      //On gére les actionneurs associé aux thermostats
      foreach ($actuators as $action) {
        if (!isset($action['type'])) $action['type'] = 0;
        //On gere les actionneurs sur consigne
        if ($action['type'] != $type) continue;
        if (!isset($action['offset'])) $action['offset'] = 0;
        $tmpOpt = $opts;
        $tmpOpt['slider'] += $action['offset'];
        //Convertir array en string (json php)?
        log::add('boilerThermostat', 'info', 'Execution de la commande : ' . $action['cmd'] . " avec les options " . json_encode($tmpOpt));
        scenarioExpression::createAndExec('action', $action['cmd'], $tmpOpt);
      }
    }
  }

  public function preInsert()
  {
    //log::add('boilerThermostat', 'debug', 'PreInsert');
    $this->setIsEnable(1);
    if ($this->getConfiguration('type') == 'Thermostat') {
      $this->setConfiguration('hysteresis', 0.25);
      $this->setConfiguration('inertiaFactor', 0);
    }
  }

  /*public function postInsert() {
    //log::add('boilerThermostat', 'debug', 'PostInsert');
  }*/

  /*public function preUpdate() {
  //log::add('boilerThermostat', 'debug', 'PreUpdate');
  }*/

  /*public function postUpdate() {
  //log::add('boilerThermostat', 'debug', 'PostUpdate');
  }*/

  /*public function preSave() {
  //log::add('boilerThermostat', 'debug', 'PreSave');
  }*/

  public function setAppMobileParameters($forceParam = false)
  {
    $params = array(
      'mode' => 'THERMOSTAT_MODE',
      'modeAction' => 'THERMOSTAT_SET_MODE',
      'associatedTemperatureSensor' => 'THERMOSTAT_TEMPERATURE',
      'setPoint' => 'THERMOSTAT_SETPOINT',
      'adjustedSetPoint' => 'DONT',
      'setPointActuator' => 'THERMOSTAT_SET_SETPOINT',
      'status' => 'THERMOSTAT_STATE',
      'statusName' => 'THERMOSTAT_STATE_NAME',
      'On' => 'ENERGY_ON',
      'Off' => 'ENERGY_OFF',
      'state' => 'ENERGY_STATE',
      'pourcentAdjustment' => 'GENERIC_INFO',
      'presenceMode' => 'MODE_STATE',
      'presenceAuto' => 'MODE_SET_STATE',
      'presenceIn' => 'MODE_SET_STATE',
      'presenceOut' => 'MODE_SET_STATE'
    );

    foreach ($this->getCmd() as $cmd) {
      if ($cmd->getGeneric_type() == '' || $forceParam) {
        $cmd->setGeneric_type($params[$cmd->getLogicalId()]);
        $cmd->save();
      }
    }
  }

  public function postSave()
  {
    try {
      $eqLogicsManager = self::byTypeAndSearhConfiguration('boilerThermostat', 'Manager');
      if (!count($eqLogicsManager) > 0) {
        self::createEquipment('Manager', 'Thermostats Manager');
      }
      if ($this->getConfiguration('type') == 'Thermostat') {
        $this->addThermostatCmds();
      } elseif ($this->getConfiguration('type') == 'Manager') $this->addManagerCmds();
      else throw new Exception('Unable to create equipment, unknown eqp config type : ' . $this->getConfiguration('type'));

      //Parametrage des commandes pour l'app mobile
      $this->setAppMobileParameters();
    } catch (Exception $e) {
      log::add('boilerThermostat', 'error', displayExeption($e) . ', errCode : ' . $e->getCode());
      throw $e;
    }

    //Manage listener for setPoint return
    try {
      $opt = array();
      $opt['eqpID'] = $this->getId();
      $listener = listener::byClassAndFunction('boilerThermostat', 'runActuatorLogic', $opt);
      //On réinitialise les evenement
      if (is_object($listener)) {
        $listener->setEvent(array());
        $listener->save();
      }

      $actuators = $this->getConfiguration('childActuators');
      //On gére les actionneurs associé aux thermostats
      foreach ($actuators as $actuator) {
        if (!isset($actuator['type'])) $actuator['type'] = 0;
        //On gere les actionneurs sur consigne
        if ($actuator['type'] != 0 || $actuator['isSetPointController'] == 0) continue;
        //Récuperation de l'info de consigne lié a l'actionneur
        $actuatorCmd = cmd::byId(str_replace('#', '', $actuator['cmd']));
        $actuatorCmdInfo = $actuatorCmd->getValue();
        if (!$actuatorCmdInfo || $actuatorCmdInfo == '') {
          log::add('boilerThermostat', 'error', "Impossible d'activer le retour de l'actionneur " . $actuator['cmd'] . ", le commande d'info associé à l'action n'est pas defini");
          continue;
        }
        if (!is_object($listener)) {
          $listener = new listener();
          $listener->setClass('boilerThermostat');
          $listener->setFunction('runActuatorLogic');
          $listener->addEvent($actuatorCmdInfo);
          $listener->setOption($opt);
          $listener->save();
        } else {
          $listener->addEvent($actuatorCmdInfo);
          $listener->save();
        }
      }

      //Si le listener n'est plus nécessaire, on le supprime
      if (is_object($listener) && count($listener->getEvent()) == 0) $listener->remove();
    } catch (Exception $e) {
      log::add('boilerThermostat', 'error', displayExeption($e) . ', errCode : ' . $e->getCode());
      throw $e;
    }
  }

  /*public function preAjax() {

}*/

  public function postAjax()
  {
    if ($this->getConfiguration('type') != 'Thermostat') return;
    else $this->manageCmdOrder();
  }

  public function preRemove()
  {
    $opt = array();
    $opt['eqpID'] = $this->getId();
    $listener = listener::byClassAndFunction('boilerThermostat', 'runActuatorLogic', $opt);
    if (is_object($listener)) $listener->remove();
  }

  /*public function postRemove() {
//log::add('boilerThermostat', 'debug', 'PostRemove');
}*/

  /*
* Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin
public function toHtml($_version = 'dashboard') {

}
*/

  /*     * **********************Getteur Setteur*************************** */
}

class boilerThermostatCmd extends cmd
{
  /*     * *************************Attributs****************************** */
  /*     * ***********************Methode static*************************** */
  /*     * *********************Methode d'instance************************* */
  public function preSave()
  {
    switch ($this->getLogicalId()) {
      case 'status':
        $cmdAdjustedSetPoint = $this->getEqLogic()->getCmd(null, 'adjustedSetPoint');
        $cmdAssociatedTemperatureSensor = $this->getEqLogic()->getCmd(null, 'associatedTemperatureSensor');
        $this->setValue('#' . $cmdAdjustedSetPoint->getId() . '##' . $cmdAssociatedTemperatureSensor->getId() . '#');
        break;
      case 'associatedTemperatureSensor':
        $cmd = self::byId(str_replace('#', '', $this->getEqLogic()->getConfiguration('temperatureSensor')));
        if (is_object($cmd)) {
          $this->setConfiguration('calcul', '#' . $cmd->getId() . '#');
          //$this->event($this->execute());
        } else {
          $this->setConfiguration('calcul', '');
          //$this->event('');
        }
      case 'pourcentAdjustment':
      case 'adjustedSetPoint':
        $calcul = $this->getConfiguration('calcul');
        if (strpos($this->getConfiguration('calcul'), '#' . $this->getId() . '#') !== false) {
          throw new Exception(__('Vous ne pouvez pas faire un calcul sur la valeur elle meme (boucle infinie)!!!', __FILE__));
        }
        $this->setConfiguration('calcul', $calcul);
        preg_match_all("/#([0-9]*)#/", $calcul, $matches);
        $value = '';
        foreach ($matches[1] as $cmd_id) {
          if (is_numeric($cmd_id)) {
            $cmd = self::byId($cmd_id);
            if (is_object($cmd) && $cmd->getType() == 'info') {
              $value .= '#' . $cmd_id . '#';
            }
          }
        }
        $this->setValue($value);
        break;
    }
    //log::add('boilerThermostat','debug','Etat ordre commande ' . $this->getLogicalId() . ' ordre ' . $this->getOrder());
  }

  public function postSave()
  {
    if ($this->getLogicalId() == 'status') {
      $listener = listener::byClassAndFunction('boilerThermostat', 'runBoilerLogic');
      if (!is_object($listener)) {
        $listener = new listener();
        $listener->setClass('boilerThermostat');
        $listener->setFunction('runBoilerLogic');
        $listener->setEvent(array('#' . $this->getId() . '#'));
        $listener->save();
      } else {
        $listener->addEvent($this->getId());
        $listener->save();
      }
    }
    /*if ($this->getLogicalId() == 'associatedTemperatureSensor' || $this->getLogicalId() == 'adjustedSetPoint')
    $this->event($this->execute());*/
  }

  public function preRemove()
  {
    if ($this->getLogicalId() == 'status') {
      $listener = listener::byClassAndFunction('boilerThermostat', 'runBoilerLogic');
      if (is_object($listener)) {
        $events = $listener->getEvent();
        $events = array_merge(array_diff($events, array('#' . $this->getId() . '#')));
        if (count($events) == 0) $listener->remove();
        else {
          $listener->setEvent($events);
          $listener->save();
        }
      }
    }
  }

  public function dontRemoveCmd()
  {
    return true;
  }

  public function execute($_options = array())
  {
    //log::add('boilerThermostat', 'info', 'execute '.$this->getLogicalId());
    switch ($this->getLogicalId()) {
        //Thermostat
      case 'modeAction':
        $opt = array();
        $opt['slider'] = $this->getConfiguration('option');
        $opt['mode'] = $this->getName();
        if ($opt['slider'] == 'Off')
          $opt['slider'] = $this->getEqLogic()->getCmd(null, 'setPoint')->execCmd();
        log::add('boilerThermostat', 'debug', 'Activation du mode : ' . json_encode($opt));
        $actuator = $this->getEqLogic()->getCmd(null, 'setPointActuator');
        scenarioExpression::createAndExec('action', $actuator->getId(), $opt);
        break;

      case 'associatedTemperatureSensor':
        return jeedom::evaluateExpression($this->getConfiguration('calcul'));
        break;

      case 'adjustedSetPoint':
        $resultat = round(jeedom::evaluateExpression($this->getConfiguration('calcul')), 1);
        if (!$resultat) $resultat = 0;
        if ($this->getEqLogic()->getManagerState()) {
          if ($this->getEqLogic()->getCmd('info', 'mode')->execCmd() != 'Off') $this->getEqLogic()->processChildActuators(0, $resultat);
          else $this->getEqLogic()->processChildActuators(0, 5);
        }
        return $resultat;
        break;

      case 'setPointActuator':
        //Mise a jour du mode
        $modeTxt = 'Manuel';
        if (isset($_options['mode'])) {
          $modeTxt = $_options['mode'];
        }
        $infoMode = $this->getEqLogic()->getCmd('info', 'mode');
        if (is_object($infoMode)) {
          $infoMode->event($modeTxt);
        }

        //Mise a jour de la consigne
        $cmdSetPoint = $this->getEqLogic()->getCmd(null, 'setPoint');
        $cmdSetPoint->setConfiguration('value', $_options['slider']);
        $cmdSetPoint->save();
        $cmdSetPoint->event($_options['slider']);

        //Pour mettre a jour la valeur du widget
        $this->_needRefreshWidget = true;

        return $_options['slider'];
        break;

      case 'setPoint':
        return $this->getConfiguration('value');
        break;

      case 'status':
        //Gestion de la température de référence (le pic haut ou bas)
        $mode = $this->getEqLogic()->getCmd('info', 'mode')->execCmd();
        $needSave = false;
        $actualState = $this->execCmd();
        //Pour réinitialiser l'enregistrement de l'historique
        $this->setCollectDate('');
        $outputStatus = $actualState;
        $actualTemp = $this->getEqLogic()->getCmd(null, 'associatedTemperatureSensor')->execCmd();
        $cmdSetPoint = $this->getEqLogic()->getCmd(null, 'adjustedSetPoint');
        if ($actualState && $cmdSetPoint->getConfiguration('RefTemp') > $actualTemp) {
          $cmdSetPoint->setConfiguration('RefTemp', $actualTemp);
          $needSave = true;
        } else if (!$actualState && $cmdSetPoint->getConfiguration('RefTemp') < $actualTemp) {
          $cmdSetPoint->setConfiguration('RefTemp', $actualTemp);
          $needSave = true;
        }

        $actualSetPoint = $cmdSetPoint->execCmd();
        $hysteresis = $this->getEqLogic()->getConfiguration('hysteresis');
        $inertiaFactor = $this->getEqLogic()->getConfiguration('inertiaFactor');
        if ($inertiaFactor < 0) $inertiaFactor = 0;
        if ($inertiaFactor > 90) $inertiaFactor = 90;

        log::add('boilerThermostat', 'debug', 'Thermostat : ' . $this->getEqLogic()->getHumanName() . ', ActualState : ' . $actualState . ', consigne : ' . $actualSetPoint .
          ', temp ' . $actualTemp . ', hyst : ' . $hysteresis . ', RefTemp : ' . $cmdSetPoint->getConfiguration('RefTemp') .
          ', tendance : ' . ($cmdSetPoint->getConfiguration('RefTemp') - $actualTemp > 0 ? 'down' : 'up') .
          ', coéfficent d\'inertie : ' . $inertiaFactor . '%' . ', mode : ' . $mode);

        $startHeatingTemp = $actualSetPoint - $hysteresis + ($cmdSetPoint->getConfiguration('RefTemp') - ($actualSetPoint - $hysteresis)) * $inertiaFactor / 100;
        $stopHeatingTemp = $actualSetPoint + $hysteresis - (($actualSetPoint + $hysteresis) - $cmdSetPoint->getConfiguration('RefTemp')) * $inertiaFactor / 100;

        if (!$actualState) {
          log::add('boilerThermostat', 'info', 'Thermostat : ' . $this->getEqLogic()->getHumanName() . ', Status "Arrêté" ==> Température pour chauffer : ' . $startHeatingTemp);
        } else {
          log::add('boilerThermostat', 'info', 'Thermostat : ' . $this->getEqLogic()->getHumanName() . ', Status "Chauffage" ==> Température pour arreter : ' . $stopHeatingTemp);
        }

        //Check if heating is necessary
        if ($mode == 'Off' || $this->getEqLogic()->getManagerState() == 0) {
          $displayParam = $cmdSetPoint->getDisplay('parameters');
          if ($displayParam['couleur'] != 'black') {
            $displayParam['couleur'] = 'black';
            $displayParam['fontcolor'] = 'black';
            $cmdSetPoint->setDisplay('parameters', $displayParam);
            //$cmdSetPoint->save();
            $needSave = true;
            $this->getEqLogic()->processChildActuators(2);
            //$this->getEqLogic()->getCmd(null, 'statusName')->event(__('Off', __FILE__));
            $this->getEqLogic()->CheckAndUpdateCmd('statusName', __('Off', __FILE__));
            $outputStatus = 0;
          }
        } else if (($actualTemp <= $actualSetPoint - $hysteresis
          || ($cmdSetPoint->getConfiguration('RefTemp') - $actualTemp > 0 &&
            $actualTemp <= $startHeatingTemp && $actualTemp <= $actualSetPoint + $hysteresis))) {
          if ($actualState == 0 || $actualState == '') {
            $displayParam = $cmdSetPoint->getDisplay('parameters');
            $displayParam['couleur'] = 'red';
            $displayParam['fontcolor'] = 'white';
            $cmdSetPoint->setDisplay('parameters', $displayParam);
            //$cmdSetPoint->save();
            $needSave = true;
            $this->getEqLogic()->processChildActuators(1);
            //$this->getEqLogic()->getCmd(null, 'statusName')->event(__('Chauffage', __FILE__));
            $this->getEqLogic()->CheckAndUpdateCmd('statusName', __('Chauffage', __FILE__));
          }
          $outputStatus = 1;
        }
        //Check if stop heating is necessary
        else if (($actualTemp >= $actualSetPoint + $hysteresis
          || ($cmdSetPoint->getConfiguration('RefTemp') - $actualTemp < 0 &&
            $actualTemp >= $stopHeatingTemp && $actualTemp >= $actualSetPoint - $hysteresis))) {
          $displayParam = $cmdSetPoint->getDisplay('parameters');
          if ($actualState == 1 || $actualState == '' || $displayParam['couleur'] == 'black') {
            $displayParam = $cmdSetPoint->getDisplay('parameters');
            $displayParam['couleur'] = 'grey';
            $displayParam['fontcolor'] = 'white';
            $cmdSetPoint->setDisplay('parameters', $displayParam);
            //$cmdSetPoint->save();
            $needSave = true;
            $this->getEqLogic()->processChildActuators(2);
            //$this->getEqLogic()->getCmd(null, 'statusName')->event(__('Arrêté', __FILE__));
            $this->getEqLogic()->CheckAndUpdateCmd('statusName', __('Arrêté', __FILE__));
          }
          $outputStatus = 0;
        }

        if ($needSave) $cmdSetPoint->save();
        return $outputStatus;
        break;

        //Manager
      case 'pourcentAdjustment':
        switch ($this->getEqLogic()->getConfiguration('modePresence')) {
          case 'Absent':
            return 100;
            break;
          case 'Présent':
            return 0;
            break;
        }
        return max(min(jeedom::evaluateExpression($this->getConfiguration('calcul')), 100), 0);
        break;

      case 'presenceOut':
      case 'presenceIn':
      case 'presenceAuto':
        $mode = $this->getConfiguration('mode');
        if ($this->getEqLogic()->getConfiguration('modePresence') != $mode) {
          $this->getEqLogic()->setConfiguration('modePresence', $mode);
          $this->getEqLogic()->save();
          $this->getEqLogic()->getCmd(null, 'presenceMode')->event($mode);
          $this->getEqLogic()->getCmd(null, 'pourcentAdjustment')->event($this->getEqLogic()->getCmd(null, 'pourcentAdjustment')->execute());
        }
        return $this->getConfiguration('mode');
        break;

      case 'On':
        $state = $this->getEqLogic()->getCmd(null, 'state');
        $state->setConfiguration('value', 1);
        $state->save();
        $state->event(1);
        //on recherche tous les thermostats lié a ce manager et on execute le calcul du status et on traite les actionneurs enfants avec la consigne
        foreach (eqLogic::byTypeAndSearhConfiguration('boilerThermostat', 'Thermostat') as $th) {
          if ($th->getManager()->getId() != $this->getEqLogic()->getId() || !$th->getIsEnable()) continue;
          //Need manage when cmd doesn't exist??
          $th->checkAndUpdateCmd('status', $th->getCmd('info', 'status')->execute());
          //$th->getCmd('info','status')->event($th->getCmd('info','status')->execute(),2);
          $th->checkAndUpdateCmd('adjustedSetPoint', $th->getCmd('info', 'adjustedSetPoint')->execute());
          //$th->getCmd('info','adjustedSetPoint')->event($th->getCmd('info','adjustedSetPoint')->execute(),2);
        }
        break;

      case 'Off':
        $state = $this->getEqLogic()->getCmd(null, 'state');
        $state->setConfiguration('value', 0);
        $state->save();
        $state->event(0);
        //on recherche tous les thermostats lié a ce manager et on execute le calcul du status
        foreach (eqLogic::byTypeAndSearhConfiguration('boilerThermostat', 'Thermostat') as $th) {
          if ($th->getManager()->getId() != $this->getEqLogic()->getId() || !$th->getIsEnable()) continue;
          //Need manage when cmd doesn't exist??
          //$th->getCmd('info','status')->event($th->getCmd('info','status')->execute(),2);
          $th->checkAndUpdateCmd('status', $th->getCmd('info', 'status')->execute());
        }
        break;

      case 'presenceMode':
        return $this->getEqLogic()->getConfiguration('modePresence');
        break;

      case 'state':
        return $this->getConfiguration('value');
        break;
    }
  }

  //Méthode pour imperiHome
  public function imperihomeGenerate($ISSStructure)
  {
    $eqLogic = $this->getEqLogic();
    $object = $eqLogic->getObject();
    $info_device = array(
      'id' => $this->getId(),
      'name' => $eqLogic->getName(),
      'room' => (is_object($object)) ? $object->getId() : 99999,
      'params' => array(),
    );
    //Si c'est un Thermostat
    if ($this->getEqLogic()->getConfiguration('type') == 'Thermostat') {
      $info_device['type'] = 'DevThermostat';

      $info_device['params'] = $ISSStructure[$info_device['type']]['params'];
      $info_device['params'][0]['value'] = '#' . $eqLogic->getCmd('info', 'mode')->getId() . '#';
      $info_device['params'][1]['value'] = '#' . $eqLogic->getCmd('info', 'associatedTemperatureSensor')->getId() . '#';
      $info_device['params'][2]['value'] = '#' . $eqLogic->getCmd('info', 'setPoint')->getId() . '#';
      $info_device['params'][3]['value'] = 0.5;
      $info_device['params'][4]['value'] = 'Off,Manuel';
      foreach ($eqLogic->getConfiguration('modes') as $mode) {
        $info_device['params'][4]['value'] .= ',' . $mode['name'];
      }
    } else if ($this->getEqLogic()->getConfiguration('type') == 'Manager' && $this->getLogicalId() == 'presenceMode') {
      $info_device['type'] = 'DevMultiSwitch';

      $info_device['params'] = $ISSStructure[$info_device['type']]['params'];
      $info_device['params'][0]['value'] = '#' . $this->getId() . '#';
      $info_device['params'][1]['value'] = 'Auto,Présent,Absent';
    } else if ($this->getEqLogic()->getConfiguration('type') == 'Manager' && $this->getLogicalId() == 'state') {
      $info_device['type'] = 'DevSwitch';

      $info_device['params'] = $ISSStructure[$info_device['type']]['params'];
      $info_device['params'][0]['value'] = '#' . $this->getId() . '#';
    }

    return $info_device;
  }

  public function imperihomeAction($_action, $_value)
  {
    $eqLogic = $this->getEqLogic();
    if ($_action == 'setSetPoint') {
      $cmd = $eqLogic->getCmd('action', 'setPointActuator');
      if (is_object($cmd)) {
        $cmd->execCmd(array('slider' => $_value));
      }
    }
    if ($_action == 'setMode' && $eqLogic->getConfiguration('type') == 'Thermostat') {
      if ($_value == 'Manuel') {
        $eqLogic->getCmd('info', 'mode')->event('Manuel');
        $eqLogic->getCmd('info', 'status')->event($eqLogic->getCmd('info', 'status')->execute());
      } else {
        foreach ($eqLogic->getCmd('action', 'modeAction', null, true) as $action) {
          if (is_object($action) && $action->getName() == $_value) {
            $action->execCmd();
            break;
          }
        }
      }
    }
    if ($_action == 'setChoice' && $eqLogic->getConfiguration('type') == 'Manager') {
      switch ($_value) {
        case 'Auto':
          $eqLogic->getCmd('action', 'presenceAuto')->execCmd();
          break;

        case 'Présent':
          $eqLogic->getCmd('action', 'presenceIn')->execCmd();
          break;

        case 'Absent':
          $eqLogic->getCmd('action', 'presenceOut')->execCmd();
          break;
      }
    }
    if ($_action == 'setStatus' && $eqLogic->getConfiguration('type') == 'Manager') {
      if ($_value == 0) {
        $eqLogic->getCmd('action', 'Off')->execCmd();
      } else {
        $eqLogic->getCmd('action', 'On')->execCmd();
      }
    }
  }

  public function imperihomeCmd()
  {
    if ($this->getLogicalId() == 'setPoint') {
      return true;
    }
    if ($this->getEqLogic()->getConfiguration('type') == 'Manager' && $this->getLogicalId() == 'presenceMode') {
      return true;
    }
    if ($this->getEqLogic()->getConfiguration('type') == 'Manager' && $this->getLogicalId() == 'state') {
      return true;
    }
    return false;
  }
}