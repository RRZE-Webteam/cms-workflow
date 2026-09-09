(function () {
    "use strict";

    const { CheckboxControl, PanelBody, TextControl, Button } = wp.components;
    const { dispatch, select } = wp.data;
    const { Fragment, createElement, useMemo, useState } = wp.element;
    const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
    const { registerPlugin } = wp.plugins;

    const config = window.CMSWorkflowAuthorsConfig || {};
    const strings = config.i18n || {};
    const users = Array.isArray(config.users) ? config.users : [];
    const groups = Array.isArray(config.groups) ? config.groups : [];

    const normalizeIds = (ids) =>
        Array.from(
            new Set(
                (Array.isArray(ids) ? ids : [])
                    .map((id) => Number.parseInt(id, 10))
                    .filter((id) => Number.isInteger(id) && id > 0)
            )
        );

    const normalizeSettings = (settings) => ({
        users: normalizeIds(settings && settings.users),
        groups: normalizeIds(settings && settings.groups),
    });

    const initialSettings = () => {
        const editorSettings = select("core/editor").getEditedPostAttribute(
            "workflow_author_settings"
        );

        return normalizeSettings(editorSettings || config.settings || {});
    };

    const WorkflowIcon = () =>
        createElement(
            "svg",
            {
                xmlns: "http://www.w3.org/2000/svg",
                height: "24px",
                viewBox: "0 -960 960 960",
                width: "24px",
                fill: "#1f1f1f",
                focusable: "false",
                "aria-hidden": "true",
            },
            createElement("path", {
                d: "M411-480q-28 0-46-21t-13-49l12-72q8-43 40.5-70.5T480-720q44 0 76.5 27.5T597-622l12 72q5 28-13 49t-46 21H411Zm24-80h91l-8-49q-2-14-13-22.5t-25-8.5q-14 0-24.5 8.5T443-609l-8 49ZM124-441q-23 1-39.5-9T63-481q-2-9-1-18t5-17q0 1-1-4-2-2-10-24-2-12 3-23t13-19l2-2q2-19 15.5-32t33.5-13q3 0 19 4l3-1q5-5 13-7.5t17-2.5q11 0 19.5 3.5T208-626q1 0 1.5.5t1.5.5q14 1 24.5 8.5T251-596q2 7 1.5 13.5T250-570q0 1 1 4 7 7 11 15.5t4 17.5q0 4-6 21-1 2 0 4l2 16q0 21-17.5 36T202-441h-78Zm676 1q-33 0-56.5-23.5T720-520q0-12 3.5-22.5T733-563l-28-25q-10-8-3.5-20t18.5-12h80q33 0 56.5 23.5T880-540v20q0 33-23.5 56.5T800-440ZM0-240v-63q0-44 44.5-70.5T160-400q13 0 25 .5t23 2.5q-14 20-21 43t-7 49v65H0Zm240 0v-65q0-65 66.5-105T480-450q108 0 174 40t66 105v65H240Zm560-160q72 0 116 26.5t44 70.5v63H780v-65q0-26-6.5-49T754-397q11-2 22.5-2.5t23.5-.5Zm-320 30q-57 0-102 15t-53 35h311q-9-20-53.5-35T480-370Zm0 50Zm1-280Z",
            })
        );

    const SelectionList = ({ items, selected, onChange, emptyLabel }) => {
        const [query, setQuery] = useState("");
        const [selectedOnly, setSelectedOnly] = useState(false);
        const selectedIds = useMemo(() => new Set(selected), [selected]);
        const normalizedQuery = query.trim().toLocaleLowerCase();
        const visibleItems = items.filter((item) => {
            if (selectedOnly && !selectedIds.has(item.id)) {
                return false;
            }

            if (!normalizedQuery) {
                return true;
            }

            return `${item.name || ""} ${item.description || ""}`
                .toLocaleLowerCase()
                .includes(normalizedQuery);
        });

        const toggle = (id, checked) => {
            const next = checked
                ? normalizeIds([...selected, id])
                : selected.filter((selectedId) => selectedId !== id);
            onChange(next);
        };

        return createElement(
            Fragment,
            null,
            createElement(TextControl, {
                label: strings.search || "Search",
                hideLabelFromVision: true,
                placeholder: strings.search || "Search",
                value: query,
                onChange: setQuery,
                className: "cms-workflow-authors-search",
            }),
            createElement(
                "div",
                { className: "cms-workflow-authors-filters" },
                createElement(
                    Button,
                    {
                        isPressed: !selectedOnly,
                        variant: "tertiary",
                        onClick: () => setSelectedOnly(false),
                    },
                    strings.all || "All"
                ),
                createElement(
                    Button,
                    {
                        isPressed: selectedOnly,
                        variant: "tertiary",
                        onClick: () => setSelectedOnly(true),
                    },
                    strings.selected || "Selected"
                )
            ),
            visibleItems.length
                ? createElement(
                      "div",
                      { className: "cms-workflow-authors-list" },
                      visibleItems.map((item) =>
                          createElement(CheckboxControl, {
                              key: item.id,
                              label: item.name,
                              help: item.description || undefined,
                              checked: selectedIds.has(item.id),
                              onChange: (checked) => toggle(item.id, checked),
                          })
                      )
                  )
                : createElement(
                      "p",
                      { className: "cms-workflow-authors-empty" },
                      emptyLabel
                  )
        );
    };

    const AuthorsContent = () => {
        const [settings, setSettings] = useState(initialSettings);

        const updateSettings = (key, ids) => {
            setSettings((currentSettings) => {
                const next = {
                    users: currentSettings.users,
                    groups: currentSettings.groups,
                    [key]: normalizeIds(ids),
                };

                dispatch("core/editor").editPost({
                    workflow_author_settings: next,
                });

                return next;
            });
        };

        return createElement(
            Fragment,
            null,
            createElement(
                "p",
                { className: "cms-workflow-authors-description" },
                strings.description || "Select the authors for this document"
            ),
            createElement("h3", null, strings.users || "Users"),
            createElement(SelectionList, {
                items: users,
                selected: settings.users,
                onChange: (ids) => updateSettings("users", ids),
                emptyLabel: strings.noUsers || "No users found.",
            }),
            config.hasUserGroups &&
                createElement(
                    Fragment,
                    null,
                    createElement("h3", null, strings.groups || "User group"),
                    createElement(SelectionList, {
                        items: groups,
                        selected: settings.groups,
                        onChange: (ids) => updateSettings("groups", ids),
                        emptyLabel:
                            strings.noGroups || "No user groups found.",
                    })
                )
        );
    };

    const Sidebar = () =>
        createElement(
            Fragment,
            null,
            createElement(
                PluginSidebarMoreMenuItem,
                { target: "cms-workflow-authors-sidebar" },
                strings.title || "Authors"
            ),
            createElement(
                PluginSidebar,
                {
                    name: "cms-workflow-authors-sidebar",
                    title: strings.title || "Authors",
                    icon: createElement(WorkflowIcon),
                },
                createElement(
                    PanelBody,
                    { title: strings.title || "Authors", initialOpen: true },
                    createElement(AuthorsContent)
                )
            )
        );

    registerPlugin("cms-workflow-authors", {
        icon: createElement(WorkflowIcon),
        render: Sidebar,
    });
})();
