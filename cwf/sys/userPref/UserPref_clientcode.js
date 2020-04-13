typeof window.core_sys === 'undefined' ? window.core_sys = {} : '';
window.core_sys.user_cs = {};

(function (user_cs) {

    function min_8char() {
        var val = coreWebApp.ModelBo.user_pass();
        //var result = val.match(/^(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z])([a-zA-Z0-9]{8})$/g);
        var result = val.match(/^[a-zA-Z0-9!@#$%^&*]{8,14}$/g);
        return result != null;
    }
    user_cs.min_8char = min_8char;

    function min_1upper() {
        var val = coreWebApp.ModelBo.user_pass();
        var result = val.match(/[A-Z]{1}/g);
        return result != null;
    }
    user_cs.min_1upper = min_1upper;

    function min_1lower() {
        var val = coreWebApp.ModelBo.user_pass();
        var result = val.match(/[a-z]{1}/g);
        return result != null;
    }
    user_cs.min_1lower = min_1lower;

    function min_1numb() {
        var val = coreWebApp.ModelBo.user_pass();
        var result = val.match(/[0-9]{1}/g);
        return result != null;
    }
    user_cs.min_1numb = min_1numb;

    function min_1splchar() {
        var val = coreWebApp.ModelBo.user_pass();
        var result = val.match(/[!@#$%^&*]{1}/g);
        return result != null;
    }
    user_cs.min_1splchar = min_1splchar;

    function repeat() {
        return coreWebApp.ModelBo.user_pass() == coreWebApp.ModelBo.user_pass_confirm() && coreWebApp.ModelBo.user_pass().length > 0;
    }
    user_cs.repeat = repeat;

    function isValid() {
        return self.min_8char()
                && self.min_1upper()
                && self.min_1lower()
                && self.min_1splchar()
                && self.repeat();
    }
    user_cs.isValid = isValid;

    function visibility() {
        if (coreWebApp.ModelBo.user_pass().length > 0 && coreWebApp.ModelBo.user_pass() != 'aaaaa') {
            return true;
        }
        return false;
    }
    user_cs.visibility = visibility;

}(window.core_sys.user_cs));
