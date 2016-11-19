<?php
if (!isConnect('admin')) {
  throw new Exception('{{401 - Accès non autorisé}}');
}
$plugin = plugin::byId('boilerThermostat');
sendVarToJS('eqType', 'boilerThermostat');
$eqLogics = eqLogic::byTypeAndSearhConfiguration('boilerThermostat','Thermostat');
$eqLogicsManager = eqLogic::byTypeAndSearhConfiguration('boilerThermostat','Manager');
?>

<div class="row row-overflow">
  <div class="col-lg-2 col-md-3 col-sm-4">
    <div class="bs-sidebar">
      <ul id="ul_eqLogic" class="nav nav-list bs-sidenav">
        <?php
        if (!count($eqLogicsManager))
        {
          ?>
          <a class="btn btn-default eqLogicCustomAction" style="width : 100%;margin-top : 5px;margin-bottom: 5px;" data-action="addManager"><i class="fa fa-plus-circle"></i> {{Ajouter le Manager}}</a>
          <?php
        }
        else {
          foreach ($eqLogicsManager as $eqLogic) {
            echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqLogic->getId() . '"><a>' . $eqLogic->getHumanName(true) . '</a></li>';
          }
        }
        ?>
        <a class="btn btn-default eqLogicCustomAction" style="width : 100%;margin-top : 5px;margin-bottom: 5px;" data-action="addThermostat"><i class="fa fa-plus-circle"></i> {{Ajouter Thermostat}}</a>
        <li class="filter" style="margin-bottom: 5px;"><input class="filter form-control input-sm" placeholder="{{Rechercher}}" style="width: 100%"/></li>
        <?php
        foreach ($eqLogics as $eqLogic) {
          echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqLogic->getId() . '"><a>' . $eqLogic->getHumanName(true) . '</a></li>';
        }
        ?>
      </ul>
    </div>
  </div>

  <div class="col-lg-10 col-md-9 col-sm-8 eqLogicThumbnailDisplay" style="border-left: solid 1px #EEE; padding-left: 25px;">
    <legend>{{Mes equipements}}
    </legend>

    <div class="eqLogicThumbnailContainer">
      <?php
      if (!count($eqLogicsManager))
      {
        ?>
        <div class="cursor eqLogicCustomAction" data-action="addManager" style="background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >
          <center>
            <i class="fa fa-plus-circle" style="font-size : 7em;color:#94ca02;"></i>
          </center>
          <span style="font-size : 1.1em;position:relative; top : 23px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#94ca02"><center>{{Ajouter le Manager}}</center></span>
        </div>
        <?php
      }
      else {
        foreach ($eqLogicsManager as $eqLogic) {
          echo '<div class="eqLogicDisplayCard cursor" data-eqLogic_id="' . $eqLogic->getId() . '" style="background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >';
          echo "<center>";
          echo '<img src="' . $plugin->getPathImgIcon() . '" height="105" width="95" />';
          echo "</center>";
          echo '<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;"><center>' . $eqLogic->getHumanName(true, true) . '</center></span>';
          echo '</div>';
        }
      }
      ?>
    </div>
    <br/>
    <div class="eqLogicThumbnailContainer">
      <div class="cursor eqLogicCustomAction" data-action="addThermostat" style="background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >
        <center>
          <i class="fa fa-plus-circle" style="font-size : 7em;color:#94ca02;"></i>
        </center>
        <span style="font-size : 1.1em;position:relative; top : 23px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#94ca02"><center>{{Ajouter Thermostat}}</center></span>
      </div>
      <?php
      foreach ($eqLogics as $eqLogic) {
        echo '<div class="eqLogicDisplayCard cursor" data-eqLogic_id="' . $eqLogic->getId() . '" style="background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >';
        echo "<center>";
        echo '<img src="' . $plugin->getPathImgIcon() . '" height="105" width="95" />';
        echo "</center>";
        echo '<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;"><center>' . $eqLogic->getHumanName(true, true) . '</center></span>';
        echo '</div>';
      }
      ?>
    </div>
  </div>

  <div class="col-lg-10 col-md-9 col-sm-8 eqLogic" style="border-left: solid 1px #EEE; padding-left: 25px;display: none;">
    <a class="btn btn-success eqLogicAction pull-right" data-action="save"><i class="fa fa-check-circle"></i> {{Sauvegarder}}</a>
    <a class="btn btn-danger eqLogicAction pull-right" data-action="remove"><i class="fa fa-minus-circle"></i> {{Supprimer}}</a>
    <a class="btn btn-default eqLogicAction pull-right" data-action="configure"><i class="fa fa-cogs"></i> {{Configuration avancée}}</a>

    <ul class="nav nav-tabs" role="tablist">
      <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fa fa-tachometer"></i> {{Equipement}}</a></li>
      <li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i> {{Commandes}}</a></li>
    </ul>

    <div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x: hidden;">
      <div role="tabpanel" class="tab-pane active" id="eqlogictab">
        <form class="form-horizontal">
          <fieldset>
            <legend>
              <i class="fa fa-arrow-circle-left eqLogicAction cursor" data-action="returnToThumbnailDisplay"></i>
              {{Général}}
              <i class='fa fa-cogs eqLogicAction pull-right cursor expertModeVisible' data-action='configure'></i>
              <a class="btn btn-xs btn-default pull-right eqLogicAction thermostat" data-action="copy" hidden><i class="fa fa-files-o"></i>{{Dupliquer}}</a>
            </legend>
            <div class="form-group">
              <label class="col-sm-3 control-label">{{Nom de l'équipement}}</label>
              <div class="col-sm-3">
                <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement boilerThermostat}}"/>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-3 control-label" >{{Objet parent}}</label>
              <div class="col-sm-3">
                <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                  <option value="">{{Aucun}}</option>
                  <?php
                  foreach (object::all() as $object) {
                    echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
                  }
                  ?>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-2 control-label">{{Catégorie}}</label>
              <div class="col-sm-8">
                <?php
                foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                  echo '<label class="checkbox-inline">';
                  echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
                  echo '</label>';
                }
                ?>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-3 control-label" >{{Activer}}</label>
              <div class="col-sm-8">
                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Activer}}</label>
                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
              </div>
              <!--<div class="col-sm-9">
                <input type="checkbox" class="eqLogicAttr bootstrapSwitch" data-label-text="{{Activer}}" data-l1key="isEnable" checked/>
                <input type="checkbox" class="eqLogicAttr bootstrapSwitch" data-label-text="{{Visible}}" data-l1key="isVisible" checked/>
              </div>-->
            </div>
            <div class="manager" hidden><br/></div>
            <div class="thermostat" hidden>
              <legend>{{Configuration}}</legend>
              <div class="form-group">
                <label class="col-sm-3 control-label">{{Sonde de température}}</label>
                <div class="col-sm-3">
                  <div class="input-group">
                    <input id="temperatureSensor" type='text' class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="temperatureSensor"/>
                    <span class="input-group-btn">
                      <a class="btn btn-default listCmdNumericInfo"><i class="fa fa-list-alt"></i></a>
                    </span>
                  </div>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label">{{Hystérésis}}</label>
                <div class="col-lg-1">
                  <input type="number" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="hysteresis" step="0.25" min="0" max="2" value="0.25"/>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label">{{Coéfficent d'inertie (valeur entre 0 et 90)}}</label>
                <div class="col-lg-1">
                  <input type="number" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="inertiaFactor" step="1" min="0" max="90" value="0"/>
                </div>
              </div>
              <legend>{{Modes}} <a class="btn btn-success btn-xs pull-right" id="bt_addMode" style="margin-top: 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter mode}}</a></legend>
              <div id="div_modes"></div>
              <legend>{{Actionneurs (Propagation des evenements On/Off et changement de consigne)}} <a class="btn btn-success btn-xs pull-right" id="bt_addActuator" style="margin-top: 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter actionneur}}</a></legend>
              <div id="div_actuators"></div>
            </div>
          </fieldset>
        </form>
      </div>

      <div role="tabpanel" class="tab-pane" id="commandtab">
        <table id="table_cmd" class="table table-bordered table-condensed">
          <thead>
            <tr>
              <th>{{Nom}}</th><th>{{Paramètre}}</th><th>{{Option}}</th><th>{{Action}}</th>
            </tr>
          </thead>
          <tbody>
          </tbody>
        </table>
      </div>

    </div>
  </div>

  <?php include_file('desktop', 'boilerThermostat', 'js', 'boilerThermostat');?>
  <?php include_file('core', 'plugin.template', 'js');?>