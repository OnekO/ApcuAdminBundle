/* global document,Rest,JsonHuman,basicModal,notifier */

class UserCache {
    init() {
        this.addEvents();
    }

    addEvents() {
        let rows = document.querySelectorAll('tbody tr');
        for (let row of rows) {
            row.querySelector('a.view').addEventListener('click', this.viewData.bind(this));
            row.querySelector('a.delete').addEventListener('click', this.deleteData.bind(this));
        }
    }

    viewData(evt) {
        evt.preventDefault();
        let dataDiv = evt.target.nextElementSibling;
        let promise = new Promise((resolve, reject) => {
            resolve();
        });
        if (dataDiv.getAttribute('data-cached') !== 'cached') {
            promise = Rest.getJSON(evt.target.href).then(response => {
                dataDiv.appendChild(JsonHuman.format(response.data));
                dataDiv.setAttribute('data-cached', 'cached');
            });
        }
        promise.then(() => {
            dataDiv.setAttribute('data-state', dataDiv.getAttribute('data-state') === 'shown' ? 'hidden' : 'shown');
        }).catch(error => {
            console.log(error);
        });
    }

    deleteData(evt) {
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
                    fn: () => {
                        basicModal.close();
                        Rest.getJSON(evt.target.href).then(response => {
                            if (response.deleted === true) {
                                notifier.show('Deleted', 'Entry has been deleted ', 'success', '', 2000);
                            } else {
                                notifier.show(
                                    'Error!',
                                    'Maybe the key was incorrect',
                                    'danger',
                                    '/bundles/dhservidores/img/high_priority-48.png',
                                    2000
                                );
                            }
                        });
                    }
                }
            }
        });
    }
}

let usercache = new UserCache();
usercache.init();
