import { Button, Card, CardBody, CheckboxControl, Panel, PanelBody, ToggleControl } from '@wordpress/components';
import { createRoot, useEffect, useState } from '@wordpress/element';

import './authors-settings.css';

function SettingsCard({ id, title, description, children }) {
    return (
        <Card as="section" aria-labelledby={id} className="workflow-authors-settings__card">
            <CardBody>
                <h3 id={id}>{title}</h3>
                {description && <p className="workflow-authors-settings__description">{description}</p>}
                {children}
            </CardBody>
        </Card>
    );
}

function PermissionFields({ capabilities, values, onChange }) {
    return (
        <div className="workflow-authors-settings__controls">
            {capabilities.map((capability) => (
                <CheckboxControl
                    key={capability.id}
                    label={capability.label}
                    help={capability.description || undefined}
                    checked={values[capability.id]}
                    onChange={(checked) => onChange(capability.id, checked)}
                    __nextHasNoMarginBottom
                />
            ))}
        </div>
    );
}

function AuthorsSettings({ config, form }) {
    const { postTypes, additionalGroups, optionName, i18n: strings } = config;
    const allCapabilities = [...postTypes, ...additionalGroups].flatMap((group) => group.capabilities);
    const [types, setTypes] = useState(() => Object.fromEntries(postTypes.map((type) => [type.id, type.checked])));
    const [capabilities, setCapabilities] = useState(() => Object.fromEntries(allCapabilities.map((cap) => [cap.id, cap.checked])));
    const [assignOthers, setAssignOthers] = useState(config.assignOthers);
    const enabledTypes = postTypes.filter((type) => types[type.id]);
    const activeCapabilities = [...enabledTypes, ...additionalGroups].flatMap((group) => group.capabilities);
    const dirty = assignOthers !== config.assignOthers
        || postTypes.some((type) => types[type.id] !== type.checked)
        || activeCapabilities.some((cap) => capabilities[cap.id] !== cap.checked);

    useEffect(() => {
        if (!dirty) {
            return;
        }
        let submitting = false;
        const onSubmit = () => { submitting = true; };
        const onBeforeUnload = (event) => {
            if (!submitting) {
                event.preventDefault();
                event.returnValue = '';
            }
        };
        form.addEventListener('submit', onSubmit);
        window.addEventListener('beforeunload', onBeforeUnload);
        return () => {
            form.removeEventListener('submit', onSubmit);
            window.removeEventListener('beforeunload', onBeforeUnload);
        };
    }, [dirty, form]);

    const updateCapability = (id, checked) => setCapabilities((current) => ({ ...current, [id]: checked }));

    return (
        <div className="workflow-authors-settings">
            <header className="workflow-authors-settings__intro">
                <h2>{strings.title}</h2>
                <p>{strings.description}</p>
            </header>

            {/* Serialize independently of collapsed panels, including explicit unchecked values. */}
            {postTypes.map((type) => (
                <input key={type.id} type="hidden" name={`${optionName}[post_types][${type.id}]`} value={types[type.id] ? '1' : '0'} />
            ))}
            {[...new Set(activeCapabilities.map((cap) => cap.id))].map((id) => (
                <input key={id} type="hidden" name={`${optionName}[role_caps][${id}]`} value={capabilities[id] ? '1' : '0'} />
            ))}
            <input type="hidden" name={`${optionName}[author_can_assign_others]`} value={assignOthers ? '1' : '0'} />

            <SettingsCard id="workflow-authors-types" title={strings.postTypes} description={strings.postTypesHelp}>
                <div className="workflow-authors-settings__grid">
                    {postTypes.map((type) => (
                        <CheckboxControl
                            key={type.id}
                            label={type.label}
                            help={[type.related, type.locked ? strings.locked : ''].filter(Boolean).join(' ') || undefined}
                            checked={types[type.id]}
                            disabled={type.locked}
                            onChange={(checked) => setTypes((current) => ({ ...current, [type.id]: checked }))}
                            __nextHasNoMarginBottom
                        />
                    ))}
                </div>
            </SettingsCard>

            <SettingsCard id="workflow-authors-permissions" title={strings.permissions} description={strings.permissionsHelp}>
                {enabledTypes.length === 0 ? (
                    <p>{strings.noPermissions}</p>
                ) : (
                    <div className="workflow-authors-settings__grid">
                        {enabledTypes.map((type) => (
                            <fieldset key={type.id}>
                                <legend>{type.label}</legend>
                                <PermissionFields capabilities={type.capabilities} values={capabilities} onChange={updateCapability} />
                            </fieldset>
                        ))}
                    </div>
                )}
            </SettingsCard>

            {additionalGroups.length > 0 && (
                <SettingsCard id="workflow-authors-additional" title={strings.additionalPermissions}>
                    <Panel>
                        {additionalGroups.map((group) => (
                            <PanelBody key={group.id} title={group.label} initialOpen={false}>
                                <PermissionFields capabilities={group.capabilities} values={capabilities} onChange={updateCapability} />
                            </PanelBody>
                        ))}
                    </Panel>
                </SettingsCard>
            )}

            <SettingsCard id="workflow-authors-coauthors" title={strings.coauthors}>
                <ToggleControl label={strings.assignOthers} checked={assignOthers} onChange={setAssignOthers} __nextHasNoMarginBottom />
            </SettingsCard>

            <div className="workflow-authors-settings__actions">
                <Button variant="primary" type="submit" name="submit" value="1" disabled={!dirty}>
                    {strings.save}
                </Button>
                <span role="status" aria-live="polite">{dirty ? strings.unsaved : strings.unchanged}</span>
            </div>
        </div>
    );
}

const container = document.getElementById('cms-workflow-authors-settings');
const config = window.CMSWorkflowAuthorsSettings;

if (container && config) {
    createRoot(container).render(<AuthorsSettings config={config} form={container.closest('form')} />);
}
