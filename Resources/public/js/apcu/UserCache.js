'use strict';

var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

/* global document,Rest,JsonHuman,basicModal,notifier */

var UserCache = function () {
    function UserCache() {
        _classCallCheck(this, UserCache);
    }

    _createClass(UserCache, [{
        key: 'init',
        value: function init() {
            this.addEvents();
        }
    }, {
        key: 'addEvents',
        value: function addEvents() {
            var rows = document.querySelectorAll('tbody tr');
            var _iteratorNormalCompletion = true;
            var _didIteratorError = false;
            var _iteratorError = undefined;

            try {
                for (var _iterator = rows[Symbol.iterator](), _step; !(_iteratorNormalCompletion = (_step = _iterator.next()).done); _iteratorNormalCompletion = true) {
                    var row = _step.value;

                    row.querySelector('a.view').addEventListener('click', this.viewData.bind(this));
                    row.querySelector('a.delete').addEventListener('click', this.deleteData.bind(this));
                }
            } catch (err) {
                _didIteratorError = true;
                _iteratorError = err;
            } finally {
                try {
                    if (!_iteratorNormalCompletion && _iterator.return) {
                        _iterator.return();
                    }
                } finally {
                    if (_didIteratorError) {
                        throw _iteratorError;
                    }
                }
            }
        }
    }, {
        key: 'viewData',
        value: function viewData(evt) {
            evt.preventDefault();
            var dataDiv = evt.target.nextElementSibling;
            var promise = new Promise(function (resolve, reject) {
                resolve();
            });
            if (dataDiv.getAttribute('data-cached') !== 'cached') {
                promise = Rest.getJSON(evt.target.href).then(function (response) {
                    dataDiv.appendChild(JsonHuman.format(response.data));
                    dataDiv.setAttribute('data-cached', 'cached');
                });
            }
            promise.then(function () {
                dataDiv.setAttribute('data-state', dataDiv.getAttribute('data-state') === 'shown' ? 'hidden' : 'shown');
            }).catch(function (error) {
                console.log(error);
            });
        }
    }, {
        key: 'deleteData',
        value: function deleteData(evt) {
            evt.preventDefault();
            basicModal.show({
                body: '<p>Are you sure?</p>',
                buttons: {
                    cancel: {
                        title: 'Cancel',
                        fn: basicModal.close
                    },
                    action: {
                        title: 'Continue',
                        fn: function fn() {
                            basicModal.close();
                            Rest.getJSON(evt.target.href).then(function (response) {
                                if (response.deleted === true) {
                                    notifier.show('Deleted', 'Entry has been deleted ', 'success', '', 2000);
                                } else {
                                    notifier.show('Error!', 'Maybe the key was incorrect', 'danger', '/bundles/dhservidores/img/high_priority-48.png', 2000);
                                }
                            });
                        }
                    }
                }
            });
        }
    }]);

    return UserCache;
}();

var usercache = new UserCache();
usercache.init();
//# sourceMappingURL=UserCache.js.map