<?php
if (!isConnect('admin')) {
  throw new Exception('{{401 - Accès non autorisé}}');
}
$plugin = plugin::byId('boilerThermostat');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byTypeAndSearhConfiguration($plugin->getId(), 'Thermostat');
$eqLogicsManager = eqLogic::byTypeAndSearhConfiguration('boilerThermostat', 'Manager');
?>

<div class="row row-overflow">
  <div class="col-xs-12 eqLogicThumbnailDisplay">
    <legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
    <div class="eqLogicThumbnailContainer">
      <div class="cursor eqLogicAction logoPrimary" data-action="addThermostat">
        <i class="fas fa-plus-circle"></i>
        <br>
        <span>{{Ajouter}}</span>
      </div>
      <div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
        <i class="fas fa-wrench"></i>
        <br>
        <span>{{Configuration}}</span>
      </div>
    </div>

    <legend><i class="fas fa-table"></i> {{Mes equipements}} </legend>
    <?php
    if (count($eqLogicsManager)) {
      echo '<div class="eqLogicThumbnailContainer">';
      foreach ($eqLogicsManager as $eqLogic) {
        $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
        echo '  <div class="eqLogicDisplayCard cursor ' . $opacity . '" data-eqLogic_id="' . $eqLogic->getId() . '">';
        echo '   <img src="' . $plugin->getPathImgIcon() . '"/>';
        echo '   <br>';
        echo '   <span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
        echo ' </div>';
      }
      echo '</div>';
      echo '<br>';
    }
    ?>
    <div class="eqLogicThumbnailContainer">
      <?php
      foreach ($eqLogics as $eqLogic) {
        $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
        echo '  <div class="eqLogicDisplayCard cursor ' . $opacity . '" data-eqLogic_id="' . $eqLogic->getId() . '">';
        echo '   <img src="' . $plugin->getPathImgIcon() . '"/>';
        echo '   <br>';
        echo '   <span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
        echo ' </div>';
      }
      ?>
    </div>
  </div>

  <div class="col-xs-12 eqLogic" style="display: none;">
    <div class="input-group pull-right" style="display:inline-flex">
      <span class="input-group-btn">
        <a class="btn btn-default btn-sm eqLogicAction roundedLeft" data-action="configure"><i class="fa fa-cogs"></i> {{Configuration avancée}}</a>
        <a class="btn btn-default btn-sm eqLogicAction" data-action="copy"><i class="fas fa-copy"></i> {{Dupliquer}}</a>
        <a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}</a>
        <a class="btn btn-danger btn-sm eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}</a>
      </span>
    </div>
    <ul class="nav nav-tabs" role="tablist">
      <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fa fa-arrow-circle-left"></i></a></li>
      <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fa fa-tachometer"></i> {{Equipement}}</a></li>
      <li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i> {{Avancée}}</a></li>
      <!--<li role="presentation"><a href="#advancedtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i> {{Avancée}}</a></li>-->
    </ul>
    <div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x: hidden;">
      <div role="tabpanel" class="tab-pane active" id="eqlogictab">
        <br>
        <div class="row">
          <div class="col-sm-6">
            <form class="form-horizontal">
              <fieldset>
                <div class="form-group">
                  <label class="col-sm-3 control-label">{{Nom de l'équipement}}</label>
                  <div class="col-sm-3">
                    <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                    <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement template}}" />
                  </div>
                </div>
                <div class="form-group">
                  <label class="col-sm-3 control-label">{{Objet parent}}</label>
                  <div class="col-sm-3">
                    <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                      <option value="">{{Aucun}}</option>
                      <?php
                      foreach (jeeObject::all() as $object) {
                        echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
                      }
                      ?>
                    </select>
                  </div>
                </div>
                <div class="form-group">
                  <label class="col-sm-3 control-label">{{Catégorie}}</label>
                  <div class="col-sm-9">
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
                  <label class="col-sm-3 control-label"></label>
                  <div class="col-sm-9">
                    <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked />{{Activer}}</label>
                    <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked />{{Visible}}</label>
                  </div>
                </div>
              </fieldset>
            </form>
          </div>
          <div class="col-sm-6">
            <form class="form-horizontal">
              <fieldset>
                <br>
                <div class="manager" hidden><br></div>
                <div class="thermostat" hidden>
                  <!--<legend>{{Configuration}}</legend>-->
                  <div class="form-group">
                    <label class="col-sm-3 control-label">{{Sonde de température}}</label>
                    <div class="col-sm-8">
                      <div class="input-group">
                        <input id="temperatureSensor" type='text' class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="temperatureSensor" />
                        <span class="input-group-btn">
                          <a class="btn btn-default listCmdNumericInfo"><i class="fas fa-list-alt"></i></a>
                        </span>
                      </div>
                    </div>
                  </div>
                  <div class="form-group">
                    <label class="col-sm-3 control-label">{{Hystérésis}}</label>
                    <div class="col-sm-6">
                      <input type="number" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="hysteresis" step="0.25" min="0" max="2" value="0.25" />
                    </div>
                  </div>
                  <div class="form-group">
                    <label class="col-sm-3 control-label">{{Coéfficent d'inertie (valeur entre 0 et 90)}}</label>
                    <div class="col-sm-6">
                      <input type="number" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="inertiaFactor" step="1" min="0" max="90" value="0" />
                    </div>
                  </div>
                </div>
              </fieldset>
            </form>
          </div>
        </div>
        <div class="thermostat" hidden>
          <form class="form-horizontal">
            <fieldset>
              <!--style="margin-top:5px;" -->
              <legend>{{Modes}} <a class="btn btn-success btn-xs pull-right" id="bt_addMode"><i class="fas fa-plus-circle"></i> {{Ajouter mode}}</a></legend>
              <div id="div_modes"></div>
              <!--style="margin-top:5px;" -->
              <legend>{{Actionneurs (Propagation des evenements On/Off et changement de consigne)}} <a class="btn btn-success btn-xs pull-right" id="bt_addActuator"><i class="fas fa-plus-circle"></i> {{Ajouter actionneur}}</a></legend>
              <div id="div_actuators"></div>
            </fieldset>
          </form>
        </div>
      </div>
      <div role="tabpanel" class="tab-pane" id="commandtab">
        <table id="table_cmd" class="table table-bordered table-condensed">
          <thead>
            <tr>
              <th>{{Nom}}</th>
              <th>{{Paramètre}}</th>
              <th>{{Option}}</th>
              <th>{{Action}}</th>
            </tr>
          </thead>
          <tbody>
          </tbody>
        </table>
      </div>
      <div role="tabpanel" class="tab-pane" id="advancedtab">

      </div>
    </div>
  </div>

  <?php include_file('desktop', 'boilerThermostat', 'js', 'boilerThermostat'); ?>
  <?php include_file('core', 'plugin.template', 'js'); ?>