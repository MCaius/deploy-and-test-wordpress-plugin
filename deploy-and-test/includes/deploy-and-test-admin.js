(function () {
    const config = window.deployAndTest;

    if (!config) {
        return;
    }

    const statusTabs = document.getElementById('deploy-and-test-status-tabs');
    const deployStatusContainer = document.getElementById('deploy-and-test-deploy-status');
    const testStatusContainer = document.getElementById('deploy-and-test-test-status');
    const controls = document.getElementById('deploy-and-test-controls');
    let pollTimer = null;
    let pendingPollAttempts = 0;
    let hasSeenActiveDeployRun = statusTabs && statusTabs.dataset.hasActiveDeployRun === '1';
    let hasSeenActiveTestRun = statusTabs && statusTabs.dataset.hasActiveTestRun === '1';
    const params = new URLSearchParams(window.location.search);
    const deployMessage = params.get('deploy_and_test_message') || '';
    const shouldPollForNewRun = deployMessage.indexOf('workflow dispatch started') !== -1;
    const shouldOpenTestStatus = params.get('deploy_and_test_status_tab') === 'test';
    let testSummaryIsOpen = false;

    function setButtonsDisabled(disabled, showStartingText = false) {
        if (!controls) {
            return;
        }

        controls.querySelectorAll('.deploy-and-test-action-form button').forEach((button) => {
            button.disabled = disabled;

            if (disabled && showStartingText && !button.dataset.originalText) {
                button.dataset.originalText = button.textContent;
                button.textContent = config.actionStartingText || 'Starting...';
            }
        });
    }

    function syncActionButtons() {
        if (!statusTabs) {
            return;
        }

        const hasActiveAction = statusTabs.dataset.hasActiveDeployRun === '1' || statusTabs.dataset.hasActiveTestRun === '1';

        if (hasActiveAction) {
            setButtonsDisabled(true);
        }
    }

    function updateStatus() {
        if (!deployStatusContainer) {
            return;
        }

        const body = new URLSearchParams({
            action: 'deploy_and_test_status',
            nonce: config.nonce,
        });

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body,
        })
            .then((response) => response.json())
            .then((data) => {
                if (!data || !data.success || !data.data || !data.data.html) {
                    return;
                }

                deployStatusContainer.innerHTML = data.data.html;
                statusTabs.dataset.hasActiveDeployRun = data.data.hasActiveRun ? '1' : '0';
                syncActionButtons();

                if (data.data.hasActiveRun) {
                    hasSeenActiveDeployRun = true;
                    pendingPollAttempts = 0;
                    startPolling();
                    return;
                }

                if (!hasSeenActiveDeployRun && pendingPollAttempts > 0) {
                    pendingPollAttempts -= 1;
                    startPolling();
                    return;
                }

                stopPolling();
                cleanWorkflowQueryParams();

                if (hasSeenActiveDeployRun) {
                    window.location.reload();
                }
            })
            .catch(() => {
                stopPolling();
            });
    }

    function updateTestStatus() {
        if (!testStatusContainer || testSummaryIsOpen) {
            stopPolling();
            return;
        }

        const body = new URLSearchParams({
            action: 'deploy_and_test_test_status',
            nonce: config.nonce,
        });

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body,
        })
            .then((response) => response.json())
            .then((data) => {
                if (!data || !data.success || !data.data || !data.data.html) {
                    return;
                }

                testStatusContainer.innerHTML = data.data.html;
                bindLoadSummaryButtons(testStatusContainer);
                statusTabs.dataset.hasActiveTestRun = data.data.hasActiveRun ? '1' : '0';
                syncActionButtons();

                if (data.data.hasActiveRun) {
                    hasSeenActiveTestRun = true;
                    pendingPollAttempts = 0;
                    startPolling();
                    return;
                }

                if (!hasSeenActiveTestRun && pendingPollAttempts > 0) {
                    pendingPollAttempts -= 1;
                    startPolling();
                    return;
                }

                stopPolling();
                cleanWorkflowQueryParams();

                hasSeenActiveTestRun = false;
            })
            .catch(() => {
                stopPolling();
            });
    }

    function updateActiveStatus() {
        if (!statusTabs || statusTabs.dataset.activeStatusTab === 'test') {
            updateTestStatus();
            return;
        }

        updateStatus();
    }

    function startPolling() {
        if (pollTimer || !statusTabs) {
            return;
        }

        pollTimer = window.setInterval(updateActiveStatus, Number(config.pollInterval) || 5000);
    }

    function activateStatusTab(target) {
        if (!statusTabs) {
            return;
        }

        statusTabs.dataset.activeStatusTab = target;

        document.querySelectorAll('[data-deploy-and-test-status-tab]').forEach((tab) => {
            const isActive = tab.dataset.deployAndTestStatusTab === target;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        document.querySelectorAll('[data-deploy-and-test-status-panel]').forEach((panel) => {
            const isActive = panel.dataset.deployAndTestStatusPanel === target;
            panel.classList.toggle('is-active', isActive);
            panel.hidden = !isActive;
        });

        updateActiveStatus();
    }

    function cleanWorkflowQueryParams() {
        const cleanParams = new URLSearchParams(window.location.search);
        let changed = false;

        ['deploy_and_test_status', 'deploy_and_test_message', 'deploy_and_test_status_tab'].forEach((key) => {
            if (cleanParams.has(key)) {
                cleanParams.delete(key);
                changed = true;
            }
        });

        if (!changed || !window.history || !window.history.replaceState) {
            return;
        }

        const query = cleanParams.toString();
        const cleanUrl = `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash}`;
        window.history.replaceState({}, document.title, cleanUrl);
    }

    function setupStatusTabs() {
        document.querySelectorAll('[data-deploy-and-test-status-tab]').forEach((tab) => {
            tab.addEventListener('click', () => {
                activateStatusTab(tab.dataset.deployAndTestStatusTab);
            });
        });

        if (shouldOpenTestStatus) {
            activateStatusTab('test');
        }
    }

    function bindLoadSummaryButtons(scope) {
        scope.querySelectorAll('.deploy-and-test-load-test-summary').forEach((button) => {
            if (button.dataset.bound === '1') {
                return;
            }

            button.dataset.bound = '1';
            button.addEventListener('click', () => {
                if (button.disabled || statusTabs && statusTabs.dataset.hasActiveTestRun === '1') {
                    return;
                }

                const output = scope.querySelector(`[data-test-summary-for="${button.dataset.runId}"]`);

                if (!output) {
                    return;
                }

                testSummaryIsOpen = true;
                stopPolling();
                cleanWorkflowQueryParams();
                button.disabled = true;
                button.textContent = config.loadingSummaryText || 'Loading summary...';
                output.innerHTML = `<p class="deploy-and-test-muted">${config.loadingSummaryHint || 'Downloading test summary from GitHub. This can take a minute.'}</p>`;

                const body = new URLSearchParams({
                    action: 'deploy_and_test_test_summary',
                    nonce: config.nonce,
                    run_id: button.dataset.runId,
                });

                fetch(config.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body,
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (!data || !data.success || !data.data || !data.data.html) {
                            output.innerHTML = `<p class="deploy-and-test-muted">${data && data.data && data.data.message ? data.data.message : 'Could not load summary.'}</p>`;
                            return;
                        }

                        output.innerHTML = data.data.html;
                    })
                    .catch(() => {
                        output.innerHTML = '<p class="deploy-and-test-muted">Could not load summary.</p>';
                    })
                    .finally(() => {
                        button.textContent = config.loadSummaryText || 'Load test summary';
                        button.disabled = false;
                    });
            });
        });
    }

    function stopPolling() {
        if (!pollTimer) {
            return;
        }

        window.clearInterval(pollTimer);
        pollTimer = null;
    }

    function setupTestActionRows() {
        const addButton = document.getElementById('deploy-and-test-add-test-action');
        const body = document.getElementById('deploy-and-test-test-actions-body');
        const template = document.getElementById('deploy-and-test-test-action-template');

        if (!addButton || !body || !template) {
            return;
        }

        function nextIndex() {
            return Date.now().toString();
        }

        function bindRemoveButtons(scope) {
            scope.querySelectorAll('.deploy-and-test-remove-test-action').forEach((button) => {
                if (button.dataset.bound === '1') {
                    return;
                }

                button.dataset.bound = '1';
                button.addEventListener('click', () => {
                    const row = button.closest('.deploy-and-test-test-action-row');

                    if (row) {
                        row.remove();
                    }
                });
            });
        }

        addButton.addEventListener('click', () => {
            const wrapper = document.createElement('tbody');
            wrapper.innerHTML = template.innerHTML.split('__index__').join(nextIndex()).trim();
            const row = wrapper.firstElementChild;

            if (row) {
                body.appendChild(row);
                bindRemoveButtons(row);
            }
        });

        bindRemoveButtons(body);
    }

    function setupTestEnvironmentRows() {
        const addButton = document.getElementById('deploy-and-test-add-test-environment');
        const body = document.getElementById('deploy-and-test-test-environments-body');
        const template = document.getElementById('deploy-and-test-test-environment-template');

        if (!addButton || !body || !template) {
            return;
        }

        function nextIndex() {
            return Date.now().toString();
        }

        function bindRemoveButtons(scope) {
            scope.querySelectorAll('.deploy-and-test-remove-test-environment').forEach((button) => {
                if (button.dataset.bound === '1') {
                    return;
                }

                button.dataset.bound = '1';
                button.addEventListener('click', () => {
                    const row = button.closest('.deploy-and-test-test-environment-row');

                    if (row) {
                        row.remove();
                    }
                });
            });
        }

        addButton.addEventListener('click', () => {
            const wrapper = document.createElement('tbody');
            wrapper.innerHTML = template.innerHTML.split('__index__').join(nextIndex()).trim();
            const row = wrapper.firstElementChild;

            if (row) {
                body.appendChild(row);
                bindRemoveButtons(row);
            }
        });

        bindRemoveButtons(body);
    }

    function setupTestEnvironmentSelect() {
        const select = document.getElementById('deploy-and-test-test-environment-select');

        if (!select) {
            return;
        }

        function syncTestEnvironment() {
            document.querySelectorAll('input[name="test_environment"]').forEach((input) => {
                input.value = select.value;
            });
        }

        select.addEventListener('change', syncTestEnvironment);
        syncTestEnvironment();
    }

    function setupSubtabs() {
        document.querySelectorAll('[data-deploy-and-test-subtabs]').forEach((container) => {
            const tabs = Array.from(container.querySelectorAll('[data-deploy-and-test-subtab]'));
            const panels = Array.from(container.querySelectorAll('[data-deploy-and-test-subtab-panel]'));

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => {
                    const target = tab.dataset.deployAndTestSubtab;

                    tabs.forEach((item) => {
                        const isActive = item === tab;
                        item.classList.toggle('is-active', isActive);
                        item.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    });

                    panels.forEach((panel) => {
                        const isActive = panel.dataset.deployAndTestSubtabPanel === target;
                        panel.classList.toggle('is-active', isActive);
                        panel.hidden = !isActive;
                    });
                });
            });
        });
    }

    document.querySelectorAll('.deploy-and-test-action-form').forEach((form) => {
        form.addEventListener('submit', () => {
            setButtonsDisabled(true, true);
        });
    });

    setupTestActionRows();
    setupTestEnvironmentRows();
    setupTestEnvironmentSelect();
    setupSubtabs();
    setupStatusTabs();
    syncActionButtons();
    if (testStatusContainer) {
        bindLoadSummaryButtons(testStatusContainer);
    }

    if (statusTabs && (statusTabs.dataset.hasActiveDeployRun === '1' || statusTabs.dataset.hasActiveTestRun === '1')) {
        startPolling();
    }

    if (statusTabs && shouldPollForNewRun) {
        pendingPollAttempts = 24;
        startPolling();
        window.setTimeout(updateActiveStatus, 1500);
    }
})();
