import { Button, Card, CardBody, CardFooter, ToggleControl } from '@wordpress/components';
import { createRoot, useEffect, useState } from '@wordpress/element';

import './module-overview.css';

function ModuleCard({ module, enabled, onChange, strings }) {
    const titleId = `workflow-module-${module.slug}-title`;
    const descriptionId = `workflow-module-${module.slug}-description`;

    return (
        <Card className="workflow-overview__card" as="section" aria-labelledby={titleId}>
            <CardBody className="workflow-overview__body">
                <h3 id={titleId}>{module.title}</h3>
                <p id={descriptionId}>{module.description}</p>
                {/* The PHP save handler needs an explicit zero for disabled modules. */}
                <input type="hidden" name={module.name} value={enabled ? '1' : '0'} />
            </CardBody>
            <CardFooter className="workflow-overview__footer">
                <ToggleControl
                    label={enabled ? strings.enabled : strings.disabled}
                    aria-label={`${module.title}: ${enabled ? strings.enabled : strings.disabled}`}
                    aria-describedby={descriptionId}
                    checked={enabled}
                    __nextHasNoMarginBottom
                    onChange={onChange}
                />
                {enabled && module.configureUrl && (
                    <Button
                        variant="link"
                        href={module.configureUrl}
                        aria-label={`${module.title}: ${module.configureLabel}`}
                    >
                        {module.configureLabel}
                    </Button>
                )}
                {enabled && !module.activated && module.configurable && (
                    <p className="workflow-overview__pending">{strings.activationPending}</p>
                )}
            </CardFooter>
        </Card>
    );
}

function ModuleOverview({ modules, strings, form }) {
    const [values, setValues] = useState(() => Object.fromEntries(
        modules.map((module) => [module.slug, module.activated])
    ));
    const dirty = modules.some((module) => values[module.slug] !== module.activated);

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

    return (
        <section className="workflow-overview" aria-labelledby="workflow-overview-title">
            <header className="workflow-overview__intro">
                <h2 id="workflow-overview-title">{strings.title}</h2>
                <p>{strings.description}</p>
            </header>
            {modules.length === 0 ? (
                <p>{strings.empty}</p>
            ) : (
                <>
                    <div className="workflow-overview__grid">
                        {modules.map((module) => (
                            <ModuleCard
                                key={module.slug}
                                module={module}
                                enabled={values[module.slug]}
                                strings={strings}
                                onChange={(checked) => setValues((current) => ({
                                    ...current,
                                    [module.slug]: checked,
                                }))}
                            />
                        ))}
                    </div>
                    <div className="workflow-overview__actions">
                        <Button variant="primary" type="submit" name="submit" value="1" disabled={!dirty}>
                            {strings.save}
                        </Button>
                        <span role="status" aria-live="polite">
                            {dirty ? strings.unsaved : strings.unchanged}
                        </span>
                    </div>
                </>
            )}
        </section>
    );
}

const container = document.getElementById('cms-workflow-module-overview');
const config = window.CMSWorkflowOverview;

// The PHP form remains usable when this script or its WordPress dependencies cannot load.
if (container && config) {
    createRoot(container).render(
        <ModuleOverview
            modules={config.modules}
            strings={config.i18n}
            form={container.closest('form')}
        />
    );
}
