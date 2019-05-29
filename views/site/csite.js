window.csite = {};

(function (csite) {

    function viewModel() {
        var self = this;
        self.pwd = ko.observable("");
        self.pwd_repeat = ko.observable("");

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
    }

    function doBinding() {
        csite.model = new viewModel();
        ko.applyBindings(csite.model);
    }
    csite.doBinding = doBinding;

    $.ready(csite.doBinding());

}(window.csite));
