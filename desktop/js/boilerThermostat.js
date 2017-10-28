
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

/*$("#tempSelector").delegate(".listEquipementInfo", 'click', function () {
var el = $(this);
jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function (result) {
var tempSensor = el.closest('div').find('.eqLogicAttr[data-l1key=configuration][data-l2key=temperatureSensor]');
tempSensor.val(result.human);
});
});*/

$('#bt_addActuator').on('click', function () {
    addActuator(null,'{{Actionneur}}',$('#div_actuators'))
});

$('#bt_addMode').on('click', function () {
    bootbox.prompt("{{Nom du mode ?}}", function (result) {
        if (result !== null) {
            addMode({name: result},$('#div_modes'));
        }
    });
});

$("body").delegate(".listCmdChildActuatorAction", 'click', function () {
    var el = $(this).closest('.childActuator').find('.expressionAttr[data-l1key=cmd]');
    //jeedom.cmd.getSelectModal({cmd: {type: 'action', subType:'slider'}}, function (result) {
    jeedom.cmd.getSelectModal({cmd: {type: 'action'}}, function (result) {
        el.value(result.human);
    });
});

$("body").delegate(".listCmdOtherAction", 'click', function () {
    var el = $(this).closest('.form-group').find('.configKey');
    jeedom.cmd.getSelectModal({cmd: {type: 'action', subType:'other'}}, function (result) {
        if (el.attr('data-concat') == 1) {
            el.atCaret('insert', result.human);
        } else {
            el.value(result.human);
        }
    });
});

$("body").delegate(".listCmdNumericInfo", 'click', function () {
    var el = $(this).closest('.form-group').find('.eqLogicAttr');
    jeedom.cmd.getSelectModal({cmd: {type: 'info', subType:'numeric'}}, function (result) {
        if (el.attr('data-concat') == 1) {
            el.atCaret('insert', result.human);
        } else {
            el.value(result.human);
        }
    });
});

 $("#div_actuators").delegate('.bt_removeAction', 'click', function () {
    $(this).closest('.childActuator').remove();
});

$("#div_modes").delegate('.bt_removeAction', 'click', function () {
   $(this).closest('.mode').remove();
});

$('.eqLogicCustomAction[data-action=addManager]').off('click').on('click', function() {
  bootbox.prompt("{{Nom de l'équipement ?}}", function(result) {
    if (result !== null) {
      jeedom.eqLogic.save({
        type: eqType,
        eqLogics: [{name: result, configuration: {type: 'Manager'}}],
        error: function (error) {
          $('#div_alert').showAlert({message: error.message, level: 'danger'});
        },
        success: function (_data) {
          var vars = getUrlVars();
          var url = 'index.php?';
          for (var i in vars) {
            if (i != 'id' && i != 'saveSuccessFull' && i != 'removeSuccessFull') {
              url += i + '=' + vars[i].replace('#', '') + '&';
            }
          }
          modifyWithoutSave = false;
          url += 'id=' + _data.id + '&saveSuccessFull=1';
          loadPage(url);
        }
      });
    }
  });
});

$('.eqLogicCustomAction[data-action=addThermostat]').off('click').on('click', function() {
  bootbox.prompt("{{Nom de l'équipement ?}}", function(result) {
    if (result !== null) {
      jeedom.eqLogic.save({
        type: eqType,
        eqLogics: [{name: result, configuration: {type: 'Thermostat'}}],
        error: function (error) {
          $('#div_alert').showAlert({message: error.message, level: 'danger'});
        },
        success: function (_data) {
          var vars = getUrlVars();
          var url = 'index.php?';
          for (var i in vars) {
            if (i != 'id' && i != 'saveSuccessFull' && i != 'removeSuccessFull') {
              url += i + '=' + vars[i].replace('#', '') + '&';
            }
          }
          modifyWithoutSave = false;
          url += 'id=' + _data.id + '&saveSuccessFull=1';
          loadPage(url);
        }
      });
    }
  });
});

$("#table_cmd").delegate(".listEquipementInfo", 'click', function () {
    var el = $(this);
    jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function (result) {
        var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=' + el.data('input') + ']');
        calcul.atCaret('insert', result.human);
    });
});

$("#table_cmd").sortable({
  axis: "y",
  cursor: "move",
  items: ".cmd",
  placeholder: "ui-state-highlight",
  tolerance: "intersect",
  forcePlaceholderSize: true
});

function printEqLogic(_eqLogic) {
    if (_eqLogic.configuration.type == 'Thermostat')
    {
      $('.thermostat').show();
      $('.manager').hide();
    }
    else if (_eqLogic.configuration.type == 'Manager')
    {
      $('.manager').show();
      $('.thermostat').hide();
    }

    $('#div_actuators').empty();
    $('#div_modes').empty();
    if (isset(_eqLogic.configuration)) {
        if (isset(_eqLogic.configuration.childActuators)) {
            for (var i in _eqLogic.configuration.childActuators) {
                addActuator(_eqLogic.configuration.childActuators[i],'{{Actionneur}}',$('#div_actuators'));
            }
        }
    }

    if (isset(_eqLogic.configuration.modes)) {
            for (var i in _eqLogic.configuration.modes) {
                addMode(_eqLogic.configuration.modes[i],$('#div_modes'));
            }
        }
}

function saveEqLogic(_eqLogic) {
    if (!isset(_eqLogic.configuration)) {
        _eqLogic.configuration = {};
    }
    _eqLogic.configuration.childActuators = $('#div_actuators').find('.childActuator').getValues('.expressionAttr');
    _eqLogic.configuration.modes = $('#div_modes').find('.mode').getValues('.expressionAttr');
    return _eqLogic;
}

function addMode(_mode,_el){
  if (!isset(_mode)) {
      _mode = {};
  }
  //var input = '';
  //var button = 'btn-default';

  var div = '<div class="form-group mode">';
  div += '<label class="col-sm-1 control-label">' + _mode.name + '</label>';
  div += '<div class="col-sm-2">';
  div += '<input class="expressionAttr" style="display: none;" data-l1key="name"/>';
  div += '<a class="btn btn-default bt_removeAction btn-sm"><i class="fa fa-minus-circle"></i></a>';
  div += '<input type="number" class="expressionAttr input-sm" data-l1key="option" step="0.5" min="10" max="25" value="19"/>';
  div += '</div>';
  div += '</div>';
  if (isset(_el)) {
      _el.append(div);
      _el.find('.mode:last').setValues(_mode, '.expressionAttr');
  }
}

function addActuator(_action, _name, _el) {
    if (!isset(_action)) {
        _action = {};
    }

    var input = '';
    var button = 'btn-default';

    var div = '<div class="childActuator form-group">';
    div += '<label class="col-sm-1 control-label">' + _name + '</label>';
    div += '<div class="col-sm-4">';
    div += '<a class="btn btn-default bt_removeAction btn-sm"><i class="fa fa-minus-circle"></i></a>';
    div += '<input style="width:250px;" class="expressionAttr input-sm cmdAction" data-l1key="cmd"/>';
    div += '<a class="btn ' + button + ' btn-sm listCmdChildActuatorAction"><i class="fa fa-list-alt"></i></a>';
    div += '</div>';
    div += '<label class="col-sm-1 control-label">' + 'Type' + '</label>';
    div += '<div class="col-sm-1">';
    div += '<select class="expressionAttr input-sm" data-l1key="type">';
    div += '<option value="0">{{Consigne}}</option>'
    div += '<option value="1">{{On}}</option>'
    div += '<option value="2">{{Off}}</option>'
    div += '</select>';
    div += '</div>';
    if (init(_action.type) == 0)
    {
      div += '<label class="col-sm-1 control-label">Offset</label>';
      div += '<div class="col-sm-1">';
      div += '<input type="number" class="expressionAttr input-sm" data-l1key="offset" step="0.1" min="-5" max="5" value="0"/>';
      div += '</div>';
    }
    div += '</div>';
    if (isset(_el)) {
        _el.append(div);
        _el.find('.childActuator:last').setValues(_action, '.expressionAttr');
    }
}

/*
* Fonction pour l'ajout de commande, appellé automatiquement par plugin.template
*/
function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
    var _cmd = {
      configuration: {}
    };
  }
  if (!isset(_cmd.configuration)) {
    _cmd.configuration = {};
  }

  //Les actions de mode ne sont géré par la configuration
  if (init(_cmd.logicalId) == 'modeAction' && init(_cmd.type) == 'action') return;
  var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
  tr += '<td style="width : 200px;">';
  tr += '<span class="cmdAttr" data-l1key="id" style="display:none;"></span>';
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" placeholder="{{Nom}}">';
  tr += '</td>';
  /*tr += '<td>';
  tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>';
  tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
  tr += '</td>';*/
  tr += '<td>';
  if (init(_cmd.configuration.calcul) != '')
  {
    tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="calcul" style="height : 33px;" placeholder="{{Calcul}}"></textarea>';
    tr += '<a class="btn btn-default cursor listEquipementInfo btn-sm" data-input="calcul"><i class="fa fa-list-alt "></i> {{Rechercher équipement}}</a>';
  }
  tr += '</td>';

  tr += '<td style="width : 200px;">';
  if (init(_cmd.type) == 'info' && (init(_cmd.subType) == 'numeric' ||init(_cmd.subType) == 'binary')) {
    tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" checked/>{{Historiser}}</label>';
  }
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/>{{Afficher}}</label>';
  if (init(_cmd.subType) == 'numeric') {
    tr += '<input class="tooltips cmdAttr form-control input-sm" data-size="mini" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width : 40%;display : inline-block;"> ';
    tr += '<input class="tooltips cmdAttr form-control input-sm" data-size="mini" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width : 40%;display : inline-block;">';
  }
  tr += '</td>';

  tr += '<td style="width : 100px;">';
  if (is_numeric(_cmd.id)) {
    tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a> ';
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
  }
  //tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';
  tr += '</td>';
  tr += '</tr>';
  $('#table_cmd tbody').append(tr);
  $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
  if (isset(_cmd.type)) {
    $('#table_cmd tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
  }
  jeedom.cmd.changeType($('#table_cmd tbody tr:last'), init(_cmd.subType));
}
