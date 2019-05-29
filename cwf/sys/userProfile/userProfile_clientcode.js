typeof window.core_sys === 'undefined' ? window.core_sys = {} : '';
window.core_sys.user_profile = {};

(function (user_profile) {
    function submitChangePass() {
        var formdata = $('#change-pass-form').serialize();
        $.ajax({
            url: '?r=cwf/sys/usersettings/change-pass',
            type: 'POST',
            dataType: 'json',
            data: formdata,
            beforeSend: function () {
                $('#brokenrules').html('');
                coreWebApp.startloading();
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = resultdata;
                if (jsonResult['status'] === 'OK') {
                    $('#userpasswordmodel-password').val('');
                    $('#userpasswordmodel-new_password').val('');
                    $('#userpasswordmodel-confirm_password').val('');
                    $('#pwd_validator').hide();
                    coreWebApp.toastmsg('info', 'Change Status', 'Password succesfully changed', false);
                    return;
                } else {
                    coreWebApp.toastmsg('warning', 'Change Status', 'Failed to change password. Fix broken rules', false);
                    var brules = jsonResult['errors'];
                    var litems = '<strong>Broken Rules</strong><div style="margin-top:5px;">';
                    for (var i = 0; i < brules.length; i++) {
                        litems += "<li>" + brules[i] + "</li>";
                    }
                    litems += '</div>';
                    $('#brokenrules').append(litems);
                    $('#divbrule').show();
                }
            }
        });
    }
    user_profile.submitChangePass = submitChangePass;
}(window.core_sys.user_profile));

window.core_sys.user_pf_cs = {};

(function (user_pf_cs) {

    function viewModel() {
        var self = this;
        self.pwd = ko.observable('');
        self.pwd_repeat = ko.observable('');

        self.min_8char = ko.computed(function () {
            var val = self.pwd();
            //var result = val.match(/^(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z])([a-zA-Z0-9]{8})$/g);
            var result = val.match(/^[a-zA-Z0-9!@#$%^&*]{8,14}$/g);
            return result != null;
        });

        self.min_1upper = ko.computed(function () {
            var val = self.pwd();
            var result = val.match(/[A-Z]{1}/g);
            return result != null;
        });

        self.min_1lower = ko.computed(function () {
            var val = self.pwd();
            var result = val.match(/[a-z]{1}/g);
            return result != null;
        });

        self.min_1numb = ko.computed(function () {
            var val = self.pwd();
            var result = val.match(/[0-9]{1}/g);
            return result != null;
        });

        self.min_1splchar = ko.computed(function () {
            var val = self.pwd();
            var result = val.match(/[!@#$%^&*]{1}/g);
            return result != null;
        });

        self.repeat = ko.computed(function () {
            return self.pwd() == self.pwd_repeat() && self.pwd().length > 0;
        });

        self.isValid = ko.pureComputed(function () {
            return self.min_8char()
                    && self.min_1upper()
                    && self.min_1lower()
                    && self.min_1splchar()
                    && self.repeat();
        });

        self.visibility = ko.computed(function () {
            if (typeof (self.pwd) != 'undefined') {
                if (self.pwd().length > 0) {
                    return true;
                }
            }
            return false;
        });
    }

    function doBinding() {
        user_pf_cs.model = new viewModel();
        ko.applyBindings(user_pf_cs.model);
    }
    user_pf_cs.doBinding = doBinding;

    $.ready(user_pf_cs.doBinding());

}(window.core_sys.user_pf_cs));

