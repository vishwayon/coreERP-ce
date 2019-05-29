window.cwf_entityextn = {};
(function (cwf_entityextn) {
    
    function bolist_filter(fltr) {
        if(typeof fltr == 'undefined' || fltr == '') {
            fltr = ' menu_type = '+$('#entity_type').val();
        } else {
            fltr += ' and menu_type = '+$('#entity_type').val();
        }
        return fltr;
    }
    cwf_entityextn.bolist_filter = bolist_filter;
    
    cwf_entityextn.newfield = false;
    function extn_field_edit(pr,prop,rw) {
        coreWebApp.showAlloc('cwf/sys','/entityExtn/EntityExtnField','cwf_entityextn.extn_field_init',
                                'cwf_entityextn.extn_field_update','cwf_entityextn.extn_field_cancel',rw);
    }
    cwf_entityextn.extn_field_edit = extn_field_edit;
    
    function extn_add_field() {
        var r = {control:-1,displayMember:'',filter:'',filterEvent:'',id:'',label:'',namedLookup:'',
                    pre:'xf_',scale:'',size:'',type:'',valueMember:'',nextRow:false, dummy:0, isOptional:false };        
        cwf_entityextn.newfield = true;
        coreWebApp.showAlloc('cwf/sys','/entityExtn/EntityExtnField','cwf_entityextn.extn_field_init',
                                'cwf_entityextn.extn_field_update','cwf_entityextn.extn_field_cancel',r);
    }
    cwf_entityextn.extn_add_field = extn_add_field;
    
    function extn_field_update(ctr,dataItem){ 
        if(dataItem[0].control() == -1 || dataItem[0].control() == '') {
            return 'Field control must be selected.';
        } 
        
        if(dataItem[0].id() == '') {
            return 'Field name can not be blank.';
        }
        if(dataItem[0].label() == '') {
            return 'Field label can not be blank.';
        }
        if(dataItem[0].type() == '') {
            return 'Field type can not be blank.';
        }
        if(dataItem[0].control() == 'SimpleCombo' || dataItem[0].control == 'SmartCombo') {
            if(dataItem[0].namedLookup() == '') {
                return 'Field namedlookup can not be blank for combo box.';
            }
            if(dataItem[0].valueMember() == '') {
                return 'Field value member can not be blank for combo box.';
            }
            if(dataItem[0].displayMember() == '') {
                return 'Field type can not be blank for combo box.';
            }
        } else {
            var found = false;
            for(var d=0; d < coreWebApp.ModelBo.custom_fields().length; d++){
                if(coreWebApp.ModelBo.custom_fields()[d]['id']() == dataItem[0].id()
                        && coreWebApp.ModelBo.custom_fields()[d]['control']() == dataItem[0].control()) {
                    found = true;
                    coreWebApp.ModelBo.custom_fields()[d]['label'](dataItem[0].label());
                    coreWebApp.ModelBo.custom_fields()[d]['type'](dataItem[0].type());
                    coreWebApp.ModelBo.custom_fields()[d]['control'](dataItem[0].control());
                    coreWebApp.ModelBo.custom_fields()[d]['size'](dataItem[0].size());
                    coreWebApp.ModelBo.custom_fields()[d]['scale'](dataItem[0].scale());
                    coreWebApp.ModelBo.custom_fields()[d]['namedLookup'](dataItem[0].namedLookup());
                    coreWebApp.ModelBo.custom_fields()[d]['valueMember'](dataItem[0].valueMember());
                    coreWebApp.ModelBo.custom_fields()[d]['displayMember'](dataItem[0].displayMember());
                    coreWebApp.ModelBo.custom_fields()[d]['filter'](dataItem[0].filter());
                    coreWebApp.ModelBo.custom_fields()[d]['filterEvent'](dataItem[0].filterEvent());
                    coreWebApp.ModelBo.custom_fields()[d]['nextRow'](dataItem[0].nextRow());
                    coreWebApp.ModelBo.custom_fields()[d]['dummy'](dataItem[0].dummy());
                    coreWebApp.ModelBo.custom_fields()[d]['isOptional'](dataItem[0].isOptional());
                    if(coreWebApp.ModelBo.custom_fields()[d]['id']() == '') {
                        coreWebApp.ModelBo.custom_fields()[d]['id'](dataItem[0].id());
                    }
                }
            }
            if(!found) {
                var r = coreWebApp.ModelBo.addNewRow('custom_fields', coreWebApp.ModelBo);
                r.id(dataItem[0].id());
                r.label(dataItem[0].label());
                r.type(dataItem[0].type());
                r.control(dataItem[0].control());
                r.size(dataItem[0].size());
                r.scale(dataItem[0].scale());
                r.namedLookup(dataItem[0].namedLookup());
                r.valueMember(dataItem[0].valueMember());
                r.displayMember(dataItem[0].displayMember());
                r.filter(dataItem[0].filter());
                r.filterEvent(dataItem[0].filterEvent());
                r.nextRow(dataItem[0].nextRow());
                r.dummy(dataItem[0].dummy());
                r.isOptional(dataItem[0].isOptional());
                coreWebApp.ModelBo.custom_fields.valueHasMutated();
            }
            cwf_entityextn.newfield = false;
            return 'OK';
        }
    }
    cwf_entityextn.extn_field_update = extn_field_update;
    
    function extn_field_init(){ 
        
    }
    cwf_entityextn.extn_field_init = extn_field_init;
    
    function extn_field_cancel(){ 
        cwf_entityextn.newfield = false;
    }
    cwf_entityextn.extn_field_cancel = extn_field_cancel;
    
    function enable_visible_smart(dataItem) { 
        if(dataItem['control']() == 'SmartCombo'
                || dataItem['control']() == 'SimpleCombo') {
            return true;
        }
        return false;
     };
     
    cwf_entityextn.enable_visible_smart=enable_visible_smart;
    
    function isnewfield(dataItem){
        return cwf_entityextn.newfield;
    }
    cwf_entityextn.isnewfield = isnewfield;
    
}(window.cwf_entityextn));