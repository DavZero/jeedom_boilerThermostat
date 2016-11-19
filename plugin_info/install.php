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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function boilerThermostat_update()
{
  //Mise a jour de la config si nécessaire pour s'adapter à la nouvelle interface plus standard
  $offHeatingConfiguration = config::byKey('offHeating','boilerThermostat','');
  log::add('boilerThermostat_update','debug','offHeatingConf ' . $offHeatingConfiguration);
  if ($offHeatingConfiguration != '' && strpos($offHeatingConfiguration,'#') === false)
  {
    log::add('boilerThermostat_update','debug','offHeatingConf update');
    $offHeatingConfiguration = '#'.$offHeatingConfiguration.'#';
    config::save('offHeating',$offHeatingConfiguration,'boilerThermostat');
  }

  $onHeatingConfiguration = config::byKey('onHeating','boilerThermostat','');
  log::add('boilerThermostat_update','debug','offHeatingConf ' . $onHeatingConfiguration);
  if ($onHeatingConfiguration != '' && strpos($onHeatingConfiguration,'#') === false)
  {
    log::add('boilerThermostat_update','debug','onHeatingConf update');
    $onHeatingConfiguration = '#'.$onHeatingConfiguration.'#';
    config::save('onHeating',$onHeatingConfiguration,'boilerThermostat');
  }

  foreach (eqLogic::byType('boilerThermostat') as $eqLogic) {
    //Passage des action/mode en action/modeAction
    foreach ($eqLogic->getCmd('action', 'mode', null, true) as $cmd) {
      log::add('boilerThermostat_update','info','Traitement du mode ' . $cmd->getName() . ' de l\'equipement ' . $eqLogic->getName());
      $cmd->setLogicalId('modeAction');
      $cmd->save();
    }

    foreach ($eqLogic->getCmd('info') as $cmd) {
      log::add('boilerThermostat_update','info','Traitement de la commande ' . $cmd->getName() . ' de l\'equipement ' . $eqLogic->getName());
      if ($cmd->getConfiguration('minValue',false) !== false)
      {
        $cmd->setConfiguration('minValueReplace',1);
        $cmd->setConfiguration('maxValueReplace',1);
        $cmd->save();
      }
    }

    if ($eqLogic->getConfiguration('type') == 'Thermostat')
    {
      log::add('boilerThermostat_update','info','Traitement du thermostat ' . $eqLogic->getName());
      $setPointActuators = $eqLogic->getConfiguration('setPointActuators');
      if (is_array($setPointActuators) && count($setPointActuators) > 0)
      {
        $eqLogic->setConfiguration('childActuators',$eqLogic->getConfiguration('setPointActuators'));
        $eqLogic->setConfiguration('setPointActuators',null);
      }
      $tempSensor = $eqLogic->getConfiguration('temperatureSensor','');
      log::add('boilerThermostat_update','debug','temperatureSensor ' . $onHeatingConfiguration);
      if ($tempSensor != '' && strpos($tempSensor,'#') === false)
      {
        log::add('boilerThermostat_update','debug','temperatureSensor update');
        $tempSensor = '#'.$tempSensor.'#';
        $eqLogic->setConfiguration('temperatureSensor',$tempSensor);
      }
      $eqLogic->save();
      $eqLogic->manageCmdOrder();
    }
    else
    {
      log::add('boilerThermostat_update','info','Traitement du manager ' . $eqLogic->getName());
      $eqLogic->save();
    }
    $eqLogic->setAppMobileParameters(true);
  }
}

function boilerThermostat_remove() {

}

?>
