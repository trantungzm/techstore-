import React, { useEffect, useState } from 'react';
import { notificationAdminApi } from '../../services/api';
import { confirmDialog, toast } from '../../utils/notify';

const inputClass = 'rounded-md border border-[var(--color-border-strong)] px-3 py-2 text-sm outline-none focus:border-[var(--color-accent)] focus:ring-2 focus:ring-blue-100';

const CHANNELS = ['System', 'Email', 'Push'];
const AUDIENCES = ['SingleUser', 'AllUsers'];

const defaultTemplateForm = () => ({
    code: '',
    name: '',
    channel: 'System',
    titleTemplate: '',
    bodyTemplate: '',
    isActive: true,
});

const defaultCampaignForm = () => ({
    name: '',
    templateId: '',
    audience: 'SingleUser',
    userId: '',
    title: '',
    message: '',
});

const AdminNotifications = () => {
    const [templates, setTemplates] = useState([]);
    const [campaigns, setCampaigns] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [activeTab, setActiveTab] = useState('templates');

    const [templateForm, setTemplateForm] = useState(defaultTemplateForm);
    const [editingTemplateId, setEditingTemplateId] = useState(null);
    const [showTemplateForm, setShowTemplateForm] = useState(false);
    const [savingTemplate, setSavingTemplate] = useState(false);

    const [campaignForm, setCampaignForm] = useState(defaultCampaignForm);
    const [showCampaignForm, setShowCampaignForm] = useState(false);
    const [savingCampaign, setSavingCampaign] = useState(false);

    const loadData = async () => {
        setLoading(true);
        setError('');

        try {
            const [templateResponse, campaignResponse] = await Promise.all([
                notificationAdminApi.getTemplates(),
                notificationAdminApi.getCampaigns(),
            ]);

            setTemplates(Array.isArray(templateResponse.data) ? templateResponse.data : []);
            setCampaigns(Array.isArray(campaignResponse.data) ? campaignResponse.data : []);
        } catch (err) {
            console.error(err);
            setError('Không thể tải dữ liệu thông báo admin.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadData();
    }, []);

    const activeTemplates = templates.filter((t) => t.IsActive);

    const handleOpenCreateTemplate = () => {
        setTemplateForm(defaultTemplateForm());
        setEditingTemplateId(null);
        setShowTemplateForm(true);
    };

    const handleEditTemplate = (template) => {
        setTemplateForm({
            code: template.Code || '',
            name: template.Name || '',
            channel: template.Channel || 'System',
            titleTemplate: template.TitleTemplate || '',
            bodyTemplate: template.BodyTemplate || '',
            isActive: template.IsActive !== false,
        });
        setEditingTemplateId(template.Id);
        setShowTemplateForm(true);
    };

    const handleCancelTemplateForm = () => {
        setShowTemplateForm(false);
        setEditingTemplateId(null);
        setTemplateForm(defaultTemplateForm());
    };

    const handleSubmitTemplate = async (e) => {
        e.preventDefault();
        setSavingTemplate(true);

        try {
            if (editingTemplateId) {
                await notificationAdminApi.updateTemplate(editingTemplateId, templateForm);
                toast.success('Cập nhật template thành công');
            } else {
                await notificationAdminApi.createTemplate(templateForm);
                toast.success('Tạo template thành công');
            }
            handleCancelTemplateForm();
            loadData();
        } catch (err) {
            console.error(err);
            toast.error(editingTemplateId ? 'Không thể cập nhật template' : 'Không thể tạo template');
        } finally {
            setSavingTemplate(false);
        }
    };

    const handleDeleteTemplate = async (template) => {
        if (!(await confirmDialog({ title: 'Xoá template', message: `Xoá template "${template.Name}"?`, tone: 'danger', confirmText: 'Xoá' }))) return;

        try {
            await notificationAdminApi.deleteTemplate(template.Id);
            toast.success('Xoá template thành công');
            loadData();
        } catch (err) {
            console.error(err);
            toast.error('Không thể xoá template');
        }
    };

    const handleOpenCreateCampaign = () => {
        setCampaignForm(defaultCampaignForm());
        setShowCampaignForm(true);
    };

    const handleCancelCampaignForm = () => {
        setShowCampaignForm(false);
        setCampaignForm(defaultCampaignForm());
    };

    const handleSubmitCampaign = async (e) => {
        e.preventDefault();

        if (!campaignForm.templateId) {
            toast.error('Vui lòng chọn template');
            return;
        }
        if (campaignForm.audience === 'SingleUser' && !campaignForm.userId) {
            toast.error('Vui lòng nhập userId cho chiến dịch gửi 1 người dùng');
            return;
        }

        setSavingCampaign(true);
        try {
            await notificationAdminApi.createCampaign({
                name: campaignForm.name,
                templateId: Number(campaignForm.templateId),
                audience: campaignForm.audience,
                userId: campaignForm.audience === 'SingleUser' ? campaignForm.userId : null,
                title: campaignForm.title || undefined,
                message: campaignForm.message || undefined,
            });
            toast.success('Tạo chiến dịch thành công');
            handleCancelCampaignForm();
            loadData();
        } catch (err) {
            console.error(err);
            toast.error(err?.response?.data?.message || 'Không thể tạo chiến dịch');
        } finally {
            setSavingCampaign(false);
        }
    };

    return (
        <div className="p-8">
            <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-3xl font-bold">Quản lý thông báo</h1>
                    <p className="mt-1 text-sm text-[var(--color-fg-muted)]">
                        Quản lý template và chiến dịch thông báo admin.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={() => setActiveTab('templates')}
                        className={`rounded-full px-4 py-2 text-sm font-semibold transition ${
                            activeTab === 'templates'
                                ? 'bg-[var(--color-primary)] text-white'
                                : 'bg-[var(--color-surface)] text-[var(--color-fg)] hover:bg-[var(--color-surface-2)]'
                        }`}
                    >
                        Templates
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('campaigns')}
                        className={`rounded-full px-4 py-2 text-sm font-semibold transition ${
                            activeTab === 'campaigns'
                                ? 'bg-[var(--color-primary)] text-white'
                                : 'bg-[var(--color-surface)] text-[var(--color-fg)] hover:bg-[var(--color-surface-2)]'
                        }`}
                    >
                        Campaigns
                    </button>
                    <button
                        type="button"
                        onClick={loadData}
                        className="rounded-full border border-[var(--color-border)] bg-[var(--color-surface)] px-4 py-2 text-sm text-[var(--color-fg)] hover:bg-[var(--color-surface-2)]"
                    >
                        Làm mới
                    </button>
                </div>
            </div>

            {error && (
                <div className="mb-4 rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-700">
                    {error}
                </div>
            )}

            {loading ? (
                <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-8 text-center text-sm text-[var(--color-fg-muted)]">
                    Đang tải dữ liệu...
                </div>
            ) : (
                <div className="space-y-6">
                    {activeTab === 'templates' ? (
                        <section className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                            <div className="mb-4 flex items-center justify-between">
                                <h2 className="text-xl font-semibold">Notification Templates</h2>
                                <button
                                    type="button"
                                    onClick={handleOpenCreateTemplate}
                                    className="inline-flex items-center gap-2 rounded-md bg-gradient-to-br from-[var(--color-accent)] to-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                                >
                                    <i className="fas fa-plus"></i>Thêm template
                                </button>
                            </div>

                            {showTemplateForm && (
                                <form onSubmit={handleSubmitTemplate} className="mb-6 space-y-4 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-2)] p-4">
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <label className="mb-1 block text-sm font-medium">Code</label>
                                            <input
                                                type="text"
                                                className={inputClass}
                                                value={templateForm.code}
                                                onChange={(e) => setTemplateForm({ ...templateForm, code: e.target.value })}
                                                placeholder="vd: order-ready"
                                                required
                                            />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-sm font-medium">Tên template</label>
                                            <input
                                                type="text"
                                                className={inputClass}
                                                value={templateForm.name}
                                                onChange={(e) => setTemplateForm({ ...templateForm, name: e.target.value })}
                                                placeholder="vd: Đơn hàng sẵn sàng lấy"
                                                required
                                            />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-sm font-medium">Kênh</label>
                                            <select
                                                className={inputClass}
                                                value={templateForm.channel}
                                                onChange={(e) => setTemplateForm({ ...templateForm, channel: e.target.value })}
                                            >
                                                {CHANNELS.map((c) => (
                                                    <option key={c} value={c}>{c}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="flex items-center pt-6">
                                            <input
                                                type="checkbox"
                                                id="templateIsActive"
                                                className="mr-2 h-4 w-4"
                                                checked={templateForm.isActive}
                                                onChange={(e) => setTemplateForm({ ...templateForm, isActive: e.target.checked })}
                                            />
                                            <label htmlFor="templateIsActive" className="text-sm font-medium">Kích hoạt</label>
                                        </div>
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-sm font-medium">Tiêu đề (title template)</label>
                                        <input
                                            type="text"
                                            className={`${inputClass} w-full`}
                                            value={templateForm.titleTemplate}
                                            onChange={(e) => setTemplateForm({ ...templateForm, titleTemplate: e.target.value })}
                                            placeholder="vd: Đơn hàng {{orderCode}} đã sẵn sàng"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-sm font-medium">Nội dung (body template)</label>
                                        <textarea
                                            className={`${inputClass} w-full`}
                                            rows={3}
                                            value={templateForm.bodyTemplate}
                                            onChange={(e) => setTemplateForm({ ...templateForm, bodyTemplate: e.target.value })}
                                            placeholder="vd: Bạn có thể đến cửa hàng để nhận đơn hàng {{orderCode}}."
                                            required
                                        />
                                    </div>
                                    <div className="flex gap-2">
                                        <button
                                            type="submit"
                                            disabled={savingTemplate}
                                            className="rounded-md bg-[var(--color-accent)] px-4 py-2 text-white hover:bg-[var(--color-accent)]/90 disabled:opacity-50"
                                        >
                                            {savingTemplate ? 'Đang lưu...' : editingTemplateId ? 'Cập nhật' : 'Tạo mới'}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={handleCancelTemplateForm}
                                            className="rounded-md border border-[var(--color-border)] px-4 py-2 hover:bg-[var(--color-surface-2)]"
                                        >
                                            Huỷ
                                        </button>
                                    </div>
                                </form>
                            )}

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-[var(--color-border)] text-left text-sm">
                                    <thead>
                                        <tr className="bg-[var(--color-surface-2)]">
                                            <th className="px-4 py-3 font-semibold">ID</th>
                                            <th className="px-4 py-3 font-semibold">Code</th>
                                            <th className="px-4 py-3 font-semibold">Name</th>
                                            <th className="px-4 py-3 font-semibold">Channel</th>
                                            <th className="px-4 py-3 font-semibold">Active</th>
                                            <th className="px-4 py-3 font-semibold">UpdatedAt</th>
                                            <th className="px-4 py-3 font-semibold text-right">Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[var(--color-border)]">
                                        {templates.length === 0 ? (
                                            <tr>
                                                <td colSpan="7" className="px-4 py-6 text-center text-sm text-[var(--color-fg-muted)]">
                                                    Chưa có template nào.
                                                </td>
                                            </tr>
                                        ) : (
                                            templates.map((template) => (
                                                <tr key={template.Id} className="hover:bg-[var(--color-surface-2)]">
                                                    <td className="px-4 py-3">{template.Id}</td>
                                                    <td className="px-4 py-3">{template.Code}</td>
                                                    <td className="px-4 py-3">{template.Name}</td>
                                                    <td className="px-4 py-3">{template.Channel}</td>
                                                    <td className="px-4 py-3">{template.IsActive ? 'Yes' : 'No'}</td>
                                                    <td className="px-4 py-3">{template.UpdatedAt}</td>
                                                    <td className="px-4 py-3 text-right">
                                                        <button
                                                            type="button"
                                                            onClick={() => handleEditTemplate(template)}
                                                            className="mr-3 font-medium text-blue-600 hover:underline"
                                                        >
                                                            Sửa
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => handleDeleteTemplate(template)}
                                                            className="font-medium text-red-600 hover:underline"
                                                        >
                                                            Xoá
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    ) : (
                        <section className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                            <div className="mb-4 flex items-center justify-between">
                                <h2 className="text-xl font-semibold">Notification Campaigns</h2>
                                <button
                                    type="button"
                                    onClick={handleOpenCreateCampaign}
                                    className="inline-flex items-center gap-2 rounded-md bg-gradient-to-br from-[var(--color-accent)] to-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                                >
                                    <i className="fas fa-plus"></i>Tạo chiến dịch
                                </button>
                            </div>

                            {showCampaignForm && (
                                <form onSubmit={handleSubmitCampaign} className="mb-6 space-y-4 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-2)] p-4">
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <label className="mb-1 block text-sm font-medium">Tên chiến dịch</label>
                                            <input
                                                type="text"
                                                className={inputClass}
                                                value={campaignForm.name}
                                                onChange={(e) => setCampaignForm({ ...campaignForm, name: e.target.value })}
                                                placeholder="vd: Thông báo khuyến mãi tháng 9"
                                                required
                                            />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-sm font-medium">Template</label>
                                            <select
                                                className={inputClass}
                                                value={campaignForm.templateId}
                                                onChange={(e) => setCampaignForm({ ...campaignForm, templateId: e.target.value })}
                                                required
                                            >
                                                <option value="">-- Chọn template --</option>
                                                {activeTemplates.map((t) => (
                                                    <option key={t.Id} value={t.Id}>{t.Name} ({t.Code})</option>
                                                ))}
                                            </select>
                                            {activeTemplates.length === 0 && (
                                                <p className="mt-1 text-xs text-amber-600">Chưa có template đang kích hoạt. Hãy tạo template trước.</p>
                                            )}
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-sm font-medium">Đối tượng nhận</label>
                                            <select
                                                className={inputClass}
                                                value={campaignForm.audience}
                                                onChange={(e) => setCampaignForm({ ...campaignForm, audience: e.target.value })}
                                            >
                                                {AUDIENCES.map((a) => (
                                                    <option key={a} value={a}>{a}</option>
                                                ))}
                                            </select>
                                        </div>
                                        {campaignForm.audience === 'SingleUser' && (
                                            <div>
                                                <label className="mb-1 block text-sm font-medium">User ID</label>
                                                <input
                                                    type="text"
                                                    className={inputClass}
                                                    value={campaignForm.userId}
                                                    onChange={(e) => setCampaignForm({ ...campaignForm, userId: e.target.value })}
                                                    placeholder="GUID người nhận"
                                                    required
                                                />
                                            </div>
                                        )}
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-sm font-medium">Tiêu đề (ghi đè template, tuỳ chọn)</label>
                                        <input
                                            type="text"
                                            className={`${inputClass} w-full`}
                                            value={campaignForm.title}
                                            onChange={(e) => setCampaignForm({ ...campaignForm, title: e.target.value })}
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-sm font-medium">Nội dung (ghi đè template, tuỳ chọn)</label>
                                        <textarea
                                            className={`${inputClass} w-full`}
                                            rows={3}
                                            value={campaignForm.message}
                                            onChange={(e) => setCampaignForm({ ...campaignForm, message: e.target.value })}
                                        />
                                    </div>
                                    <div className="flex gap-2">
                                        <button
                                            type="submit"
                                            disabled={savingCampaign}
                                            className="rounded-md bg-[var(--color-accent)] px-4 py-2 text-white hover:bg-[var(--color-accent)]/90 disabled:opacity-50"
                                        >
                                            {savingCampaign ? 'Đang tạo...' : 'Tạo chiến dịch'}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={handleCancelCampaignForm}
                                            className="rounded-md border border-[var(--color-border)] px-4 py-2 hover:bg-[var(--color-surface-2)]"
                                        >
                                            Huỷ
                                        </button>
                                    </div>
                                </form>
                            )}

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-[var(--color-border)] text-left text-sm">
                                    <thead>
                                        <tr className="bg-[var(--color-surface-2)]">
                                            <th className="px-4 py-3 font-semibold">ID</th>
                                            <th className="px-4 py-3 font-semibold">Name</th>
                                            <th className="px-4 py-3 font-semibold">TemplateId</th>
                                            <th className="px-4 py-3 font-semibold">Audience</th>
                                            <th className="px-4 py-3 font-semibold">Status</th>
                                            <th className="px-4 py-3 font-semibold">CreatedAt</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[var(--color-border)]">
                                        {campaigns.length === 0 ? (
                                            <tr>
                                                <td colSpan="6" className="px-4 py-6 text-center text-sm text-[var(--color-fg-muted)]">
                                                    Chưa có campaign nào.
                                                </td>
                                            </tr>
                                        ) : (
                                            campaigns.map((campaign) => (
                                                <tr key={campaign.Id} className="hover:bg-[var(--color-surface-2)]">
                                                    <td className="px-4 py-3">{campaign.Id}</td>
                                                    <td className="px-4 py-3">{campaign.Name}</td>
                                                    <td className="px-4 py-3">{campaign.TemplateId}</td>
                                                    <td className="px-4 py-3">{campaign.Audience}</td>
                                                    <td className="px-4 py-3">{campaign.Status}</td>
                                                    <td className="px-4 py-3">{campaign.CreatedAt}</td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    )}
                </div>
            )}
        </div>
    );
};

export default AdminNotifications;
