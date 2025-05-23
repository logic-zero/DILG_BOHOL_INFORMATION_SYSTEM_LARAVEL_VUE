<script setup>
import { ref, watch, computed } from "vue";
import { useForm, usePage, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { debounce } from "lodash";

defineOptions({
    layout: AuthenticatedLayout,
});

const pageProps = usePage().props;
const pagination = ref(pageProps.news);
const newsList = ref(pageProps.news.data ?? []);

const isModalOpen = ref(false);
const isEditMode = ref(false);
const editingNews = ref(null);
const errorMessage = ref("");
const showSuccess = ref(false);
const successMessage = ref("");
const isDeleteModalOpen = ref(false);
const newsToDelete = ref(null);
const showImagePreview = ref(false);
const isStatusModalOpen = ref(false);
const newsToToggle = ref(null);
const isEditBlockedModalOpen = ref(false);

const isSuperAdmin = computed(() => {
    return pageProps.auth.user.roles.some(role => role.name === 'Super-Admin');
});

const hasNewsPermission = (news) => {
    if (!news) return true;
    const user = pageProps.auth.user;
    return user.id === news.user_id || user.roles.some(role => ['Admin', 'Super-Admin'].includes(role.name));
};

const paginationInfo = computed(() => {
    const { from, to, total } = pagination.value;
    return from && to
        ? `Showing ${from} to ${to} of ${total} entries`
        : "No results found";
});

const formatDate = (dateString) => {
  return new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: 'long',
    day: '2-digit',
    weekday: 'long',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  }).format(new Date(dateString));
};

const previewImages = computed(() => {
  if (form.images.length > 0) {
    return Array.from(form.images).map(image => URL.createObjectURL(image));
  } else if (isEditMode.value && editingNews.value?.images) {
    return editingNews.value.images.map(image => `/news_images/${image}`);
  }
  return [];
});

const filters = ref({
    search: pageProps.filters?.search ?? "",
    status: pageProps.filters?.status ?? "",
});

watch(
    () => filters.value.search,
    debounce(() => {
        applyFilters();
    }, 500)
);

const fetchNews = (url = "/admin/news") => {
    router.get(url, filters.value, {
        preserveState: true,
        preserveScroll: true,
        only: ["news", "filters"],
        onSuccess: ({ props }) => {
            pagination.value = props.news;
            newsList.value = props.news.data;
            window.scrollTo({ top: 0, behavior: "smooth" });
        },
    });
};

const applyFilters = () => fetchNews();
const goToPage = (url) => url && fetchNews(url);

const visiblePages = computed(() => {
    const current = pagination.value.current_page;
    const last = pagination.value.last_page;
    const pages = [];

    if (last <= 1) return pages;

    if (current !== 1) {
        pages.push({ label: '« First', url: pagination.value.first_page_url });
    }

    if (current > 1) {
        pages.push({ label: '‹ Prev', url: pagination.value.prev_page_url });
    }

    pages.push({
        label: current.toString(),
        url: pagination.value.path + '?page=' + current,
        active: true
    });

    if (current < last) {
        pages.push({ label: 'Next ›', url: pagination.value.next_page_url });
    }

    if (current !== last) {
        pages.push({ label: 'Last »', url: pagination.value.last_page_url });
    }

    return pages;
});

const pageOptions = computed(() => {
    const pages = [];
    for (let i = 1; i <= pagination.value.last_page; i++) {
        pages.push({
            value: i,
            label: i.toString(),
            url: pagination.value.path + '?page=' + i
        });
    }
    return pages;
});

const form = useForm({
    id: null,
    title: "",
    caption: "",
    images: [],
});

const openModal = (news = null) => {
    isEditMode.value = !!news;
    editingNews.value = news;
    isModalOpen.value = true;
    errorMessage.value = "";
    showImagePreview.value = false;

    if (news) {
        form.id = news.id;
        form.title = news.title;
        form.caption = news.caption;
        form.images = [];
    } else {
        form.reset();
    }
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    errorMessage.value = "";
};

const showSuccessMessage = (message) => {
    successMessage.value = message;
    showSuccess.value = true;
    setTimeout(() => {
        showSuccess.value = false;
    }, 3000);
};

const submitNews = () => {
    if (isEditMode.value && editingNews.value?.status) {
        isEditBlockedModalOpen.value = true;
        closeModal();
        return;
    }

    errorMessage.value = "";

    const title = form.title.trim();
    const caption = form.caption.trim();

    if (!title) return (errorMessage.value = "Title is required.");
    if (!caption) return (errorMessage.value = "Caption is required.");
    if (!isEditMode.value && form.images.length === 0)
        return (errorMessage.value = "Please upload at least one image.");
    if (form.images.length > 5)
        return (errorMessage.value = "You can upload a maximum of 5 images.");
    if (form.images.some((image) => image.size > 5 * 1024 * 1024))
        return (errorMessage.value = "Each image must not exceed 5MB.");

    const formData = new FormData();
    formData.append("title", title);
    formData.append("caption", caption);

    if (form.images.length) {
        form.images.forEach((image) => formData.append("images[]", image));
    } else if (isEditMode.value && editingNews.value.images) {
        formData.append(
            "existing_images",
            JSON.stringify(editingNews.value.images)
        );
    }

    const onSuccess = (page) => {
        pagination.value = page.props.news;
        newsList.value = [...page.props.news.data];

        showSuccessMessage(
            isEditMode.value
                ? "News updated successfully!"
                : "News added successfully!"
        );
        closeModal();
    };

    const onError = (errors) => {
        errorMessage.value =
            errors.title?.[0] ||
            errors.caption?.[0] ||
            errors.images?.[0] ||
            "An error occurred.";
    };

    router.post(
        isEditMode.value ? `/admin/news/${form.id}` : "/admin/news",
        formData,
        {
            preserveScroll: true,
            preserveState: true,
            headers: { "Content-Type": "multipart/form-data" },
            onSuccess,
            onError,
        }
    );
};

const openDeleteModal = (news) => {
    newsToDelete.value = news;
    isDeleteModalOpen.value = true;
};

const closeDeleteModal = () => {
    newsToDelete.value = null;
    isDeleteModalOpen.value = false;
};

const deleteNews = async () => {
    if (!newsToDelete.value) return;

    try {
        await form.delete(`/admin/news/${newsToDelete.value.id}`);
        newsList.value = newsList.value.filter(
            (n) => n.id !== newsToDelete.value.id
        );
        showSuccessMessage("News deleted successfully!");
        closeDeleteModal();

        if (newsList.value.length === 0 && pagination.value.current_page > 1) {
            goToPage(`/admin/news?page=${pagination.value.current_page - 1}`);
        }
    } catch (error) {
        errorMessage.value = "Failed to delete news.";
    }
};

const openStatusModal = (news) => {
    newsToToggle.value = news;
    isStatusModalOpen.value = true;
};

const closeStatusModal = () => {
    newsToToggle.value = null;
    isStatusModalOpen.value = false;
};

const statusChoices = ref([
    { value: 0, label: 'Pending', class: 'bg-orange-400' },
    { value: 1, label: 'Approved', class: 'bg-green-500' },
    { value: 2, label: 'Declined', class: 'bg-red-500' }
]);

const toggleStatus = async (status) => {
    if (!newsToToggle.value || !isSuperAdmin.value) return;

    try {
        await router.patch(
            `/admin/news/${newsToToggle.value.id}/toggle-status`,
            { status },
            { preserveState: true, preserveScroll: true }
        );
        const newsItem = newsList.value.find((n) => n.id === newsToToggle.value.id);
        if (newsItem) newsItem.status = status;
        showSuccessMessage("Status updated successfully!");
        closeStatusModal();
    } catch (error) {
        errorMessage.value = "Failed to update status.";
    }
};

const getStatusInfo = (status) => {
    return statusChoices.value.find(choice => choice.value === status) || statusChoices.value[0];
};
</script>

<template>
    <div class="p-4">
        <h1 class="text-3xl md:text-4xl mb-5 font-bold text-gray-800 border-b-2 border-gray-500 pb-2 text-center mx-auto w-fit">
            NEWS & UPDATES
        </h1>
        <transition name="fade">
            <div v-if="showSuccess" class="fixed top-20 right-5 bg-green-500 text-white p-3 rounded shadow-lg z-50 flex items-center gap-2">
                <i class="fas fa-check-circle text-white text-lg"></i>
                <span>{{ successMessage }}</span>
            </div>
        </transition>

        <div class="flex flex-col md:flex-row md:items-center gap-2 mb-4">
            <button @click="openModal()" class="bg-blue-800 text-white px-4 py-2 rounded flex items-center gap-2 hover:bg-blue-900 w-full md:w-auto">
                <i class="fas fa-plus-circle"></i>
                Add News
            </button>

            <div class="relative flex-1 w-full md:w-auto">
                <input v-model="filters.search" @keyup.enter="applyFilters" type="text" placeholder="Search news..." class="border p-2 pl-4 pr-12 rounded w-full" />
                <div class="absolute right-1 top-1/2 transform -translate-y-1/2 text-gray-700 px-3 py-1 rounded">
                    <i class="fas fa-search"></i>
                </div>
            </div>

            <select v-model="filters.status" @change="applyFilters" class="border p-2 rounded w-full md:w-52">
                <option value="">All</option>
                <option value="approved">Approved</option>
                <option value="pending">Pending</option>
                <option value="declined">Declined</option>
            </select>
        </div>

        <div class="overflow-x-auto hidden xl:block">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-gray-200 text-gray-700 text-sm uppercase tracking-wider">
                        <th class="p-3 text-left w-[20%]">Title & Date</th>
                        <th class="p-3 text-left w-[25%]">Caption</th>
                        <th class="p-3 text-left w-[10%]">Author</th>
                        <th class="p-3 text-center w-[15%]">Images</th>
                        <th class="p-3 text-center w-[10%]">Status</th>
                        <th class="p-3 text-center w-[20%]">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="news in newsList" :key="news.id" class="border-b hover:bg-gray-50 transition">
                        <td class="p-3 text-gray-900 font-extrabold break-words">
                            <span v-if="news.is_modified" class="inline-block bg-yellow-100 text-yellow-900 text-xs px-2 py-0.5 rounded-full mt-1">Modified</span>
                            <div class="line-clamp-3">{{ news.title }}</div>
                            <span class="text-xs uppercase font-black text-blue-900 mb-8">
                                {{ formatDate(news.created_at) }}
                            </span>
                        </td>
                        <td class="p-3 text-gray-600 break-words" :title="news.caption">
                            <div class="line-clamp-6">{{ news.caption }}</div>
                        </td>
                        <td class="p-3 text-gray-700 font-bold truncate">
                            {{ news.user.name }}
                        </td>
                        <td class="p-3 text-center">
                            <div class="flex justify-center flex-wrap gap-1 max-w-[160px]">
                                <img v-for="(image, index) in news.images.slice(0, 5)" :key="index" :src="`/news_images/${image}`" alt="News Image" class="w-12 h-12 object-cover border border-gray-300" />
                            </div>
                        </td>
                        <td class="p-3 text-center">
                            <div class="flex flex-col items-center">
                                <button 
                                    @click="isSuperAdmin && openStatusModal(news)" 
                                    :class="getStatusInfo(news.status).class" 
                                    class="px-3 py-1 text-white rounded text-sm transition mb-1"
                                >
                                    {{ getStatusInfo(news.status).label }}
                                </button>
                                <span v-if="isSuperAdmin" class="text-xs text-gray-500">
                                    Click to change status
                                </span>
                            </div>
                        </td>
                        <td class="p-3 text-center">
                            <div class="flex justify-center gap-1">
                                <button @click="openModal(news)" class="bg-blue-800 hover:bg-blue-900 text-white px-3 py-1 rounded text-sm transition">
                                    {{ hasNewsPermission(news) ? 'View | Edit' : 'View' }}
                                </button>
                                <button v-if="hasNewsPermission(news)" @click="openDeleteModal(news)" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm transition">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="block xl:hidden space-y-4">
            <div v-for="news in newsList" :key="news.id" class="border rounded-lg shadow-md bg-gray-100 p-4">
                <div class="flex justify-between items-start flex-wrap gap-2">
                    <div class="flex-1 min-w-0">
                        <span v-if="news.is_modified" class="inline-block bg-yellow-100 text-yellow-800 text-xs px-2 py-0.5 rounded-full ml-1 align-middle">Modified</span>
                        <h2 class="text-lg font-extrabold line-clamp-3 mb-2 text-gray-900">
                            {{ news.title }}
                        </h2>
                        <p class="text-sm text-gray-600 line-clamp-6" :title="news.caption">
                            {{ news.caption }}
                        </p>
                        <p class="text-xs font-bold text-gray-500 mt-1">By: {{ news.user.name }}</p>
                    </div>
                    <div class="flex flex-col items-end">
                        <button @click="isSuperAdmin && openStatusModal(news)" :class="getStatusInfo(news.status).class" class="px-3 py-1 rounded text-xs whitespace-nowrap text-white mb-1">
                            {{ getStatusInfo(news.status).label }}
                        </button>
                        <span v-if="isSuperAdmin" class="text-xs text-gray-500">
                            Click to change
                        </span>
                    </div>
                </div>

                <div v-if="news.images.length" class="flex flex-wrap gap-2 mt-3">
                    <img v-for="(image, index) in news.images" :key="index" :src="`/news_images/${image}`" alt="News Image" class="w-16 h-16 object-cover border border-gray-300" />
                </div>

                <div class="mt-3 flex gap-2">
                    <button @click="openModal(news)" class="flex-1 bg-blue-800 hover:bg-blue-900 text-white text-sm px-3 py-2 rounded transition">
                        <i class="fas fa-edit"></i> {{ hasNewsPermission(news) ? 'View | Edit' : 'View' }}
                    </button>
                    <button v-if="hasNewsPermission(news)" @click="openDeleteModal(news)" class="flex-1 bg-red-600 hover:bg-red-700 text-white text-sm px-3 py-2 rounded transition">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row justify-between items-center mt-6 text-gray-700">
            <span>{{ paginationInfo }}</span>
            <div class="flex flex-wrap space-x-1 mt-2 sm:mt-0">
                <button v-for="(link, index) in visiblePages" :key="index" @click="goToPage(link.url)"
                    v-html="link.label"
                    :class="{
                        'font-bold bg-blue-300 text-gray-900': link.active,
                        'text-gray-400 cursor-not-allowed pointer-events-none': !link.url,
                    }" class="px-2 border border-gray-300 hover:bg-gray-200 transition" :disabled="!link.url">
                </button>
            </div>
        </div>

        <div v-if="pagination.last_page > 1" class="flex justify-center mt-2">
            <select
                v-model="pagination.current_page"
                @change="goToPage(pagination.path + '?page=' + pagination.current_page)"
                class="px-2 py-1 text-sm border border-gray-300 bg-white focus:outline-none focus:border-gray-400 w-auto pr-7"
            >
                <option
                    v-for="page in pageOptions"
                    :key="page.value"
                    :value="page.value"
                >
                    Page {{ page.label }}
                </option>
            </select>
        </div>

        <div v-if="isModalOpen" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex justify-center items-center px-2 py-4 z-50">
            <div class="bg-white p-2 lg:p-6 rounded shadow-lg w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <h2 class="text-xl mb-4 font-extrabold">
                    {{ isEditMode ? "Edit News" : "Add News" }}
                </h2>

                <div v-if="errorMessage" class="bg-red-500 text-white p-2 rounded mb-2 flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    {{ errorMessage }}
                </div>

                <label class="font-bold block text-gray-700">Title</label>
                <input v-model="form.title" placeholder="Enter title" class="border p-2 w-full my-2" />

                <label class="font-bold block text-gray-700">Caption</label>
                <textarea v-model="form.caption" placeholder="Enter caption" class="border p-2 w-full my-2 h-40"></textarea>

                <label class="font-bold block text-gray-700">Upload Images</label>
                <p class="text-sm text-gray-500">Max 5 images, each up to 5MB</p>
                <input type="file" multiple @change="form.images = [...$event.target.files]" class="border p-2 w-full my-2" />

                <button @click="showImagePreview = !showImagePreview" class="mt-2 text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                    <i :class="showImagePreview ? 'fas fa-eye-slash' : 'fas fa-eye'" class="mr-1"></i>
                    {{ showImagePreview ? 'Hide Preview' : 'Show Preview' }}
                </button>

                <div v-if="showImagePreview && previewImages.length" class="mt-4 border-t pt-4">
                    <div class="space-y-4">
                        <div v-for="(image, index) in previewImages" :key="index" class="relative">
                            <img :src="image" alt="Preview" class="w-full object-cover h-auto p-2 shadow-xl border border-gray-300 rounded" />
                            <span class="absolute top-2 left-2 text-white py-1 px-2 font-bold text-xs flex items-center gap-1"
                                :class="(isEditMode && editingNews?.images && index < editingNews.images.length && form.images.length === 0) ? 'bg-blue-500' : 'bg-green-500'">
                                <template v-if="isEditMode && editingNews?.images && index < editingNews.images.length && form.images.length === 0">
                                    <i class="fas fa-database"></i>
                                    Stored
                                </template>
                                <template v-else>
                                    <i class="fas fa-upload"></i>
                                    Selected
                                </template>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-4">
                    <button v-if="!editingNews || hasNewsPermission(editingNews)" @click="submitNews" class="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600 flex items-center gap-1">
                        <i class="fas fa-save"></i> Save
                    </button>
                    <button @click="closeModal" class="px-2 py-1 bg-gray-400 rounded hover:bg-gray-500">
                        {{ (!editingNews || hasNewsPermission(editingNews)) ? 'Cancel' : 'Close' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="isDeleteModalOpen" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex justify-center items-center px-2 py-4">
            <div class="bg-white p-2 lg:p-6 rounded shadow-lg w-full max-w-lg">
                <h2 class="text-xl mb-4 text-center">Are you sure?</h2>
                <p class="text-center mb-4">
                    Do you really want to delete
                    <strong>{{ newsToDelete?.title }}</strong>?
                </p>

                <div class="flex justify-center gap-2">
                    <button @click="deleteNews" class="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600">
                        Yes, Delete
                    </button>
                    <button @click="closeDeleteModal" class="px-2 py-1 bg-gray-400 rounded hover:bg-gray-500">
                        Cancel
                    </button>
                </div>
            </div>
        </div>

        <div v-if="isStatusModalOpen" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex justify-center items-center px-2 py-4">
            <div class="bg-white p-2 lg:p-6 rounded shadow-lg w-full max-w-lg">
                <h2 class="text-xl mb-4 text-center">Change Status</h2>
                <p class="text-center mb-4">
                    Current status: <span :class="getStatusInfo(newsToToggle?.status).class" class="px-2 py-1 text-sm text-white">{{ getStatusInfo(newsToToggle?.status).label }}</span>
                </p>
                
                <div class="space-y-2 mb-4">
                    <button 
                        v-for="choice in statusChoices" 
                        :key="choice.value"
                        @click="toggleStatus(choice.value)"
                        :class="choice.class"
                        class="w-full text-white px-4 py-2 rounded transition flex items-center justify-between"
                    >
                        <span>{{ choice.label }}</span>
                        <i v-if="newsToToggle?.status === choice.value" class="fas fa-check"></i>
                    </button>
                </div>

                <button @click="closeStatusModal" class="w-full px-4 py-2 bg-gray-400 rounded hover:bg-gray-500 transition">
                    Cancel
                </button>
            </div>
        </div>

        <div v-if="isEditBlockedModalOpen" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex justify-center items-center px-2 py-4">
            <div class="bg-white p-2 lg:p-6 rounded shadow-lg w-full max-w-lg">
                <h2 class="text-xl mb-4 text-center">Edit Not Allowed</h2>
                <p class="text-center mb-4">
                    This news item has been approved and can no longer be edited.
                    <br>
                    <span class="text-sm text-gray-600">Only pending news can be edited.</span>
                </p>
                <div class="flex justify-center">
                    <button @click="isEditBlockedModalOpen = false" class="px-2 py-1 bg-gray-500 text-white rounded hover:bg-gray-600">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.5s;
}

.fade-enter,
.fade-leave-to {
    opacity: 0;
}
</style>
