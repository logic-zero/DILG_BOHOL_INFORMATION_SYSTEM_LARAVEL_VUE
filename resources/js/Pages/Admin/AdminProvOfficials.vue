<script setup>
import { ref, watch, computed } from "vue";
import { useForm, usePage, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { debounce } from "lodash";

defineOptions({
    layout: AuthenticatedLayout,
});

const pageProps = usePage().props;
const officialsList = ref(pageProps.officials ?? []);

const isModalOpen = ref(false);
const isEditMode = ref(false);
const editingOfficial = ref(null);
const errorMessage = ref("");
const showSuccess = ref(false);
const successMessage = ref("");
const isDeleteModalOpen = ref(false);
const officialToDelete = ref(null);

const filters = ref({
    search: pageProps.filters?.search ?? "",
    position: pageProps.filters?.position ?? "",
});

const positions = [
    "Governor",
    "Vice Governor",
    "Member, 1st District",
    "Member, 2nd District",
    "Member, 3rd District",
    "President PCL Bohol Federation",
    "Liga ng mga Barangay",
    "SK Federation President",
];

watch(
    () => filters.value,
    debounce(() => {
        fetchOfficials();
    }, 500),
    { deep: true }
);

const fetchOfficials = () => {
    router.get("/admin/provincial-officials", filters.value, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: ({ props }) => {
            officialsList.value = props.officials;
        },
    });
};

const form = useForm({
    id: null,
    profile_image: null,
    name: "",
    position: "",
});

const openModal = (official = null) => {
    isEditMode.value = !!official;
    editingOfficial.value = official;
    isModalOpen.value = true;
    errorMessage.value = "";

    if (official) {
        form.id = official.id;
        form.name = official.name;
        form.position = official.position;
        form.profile_image = null;
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

const submitOfficial = () => {
    errorMessage.value = "";

    if (!form.name) return (errorMessage.value = "Name is required.");
    if (!form.position) return (errorMessage.value = "Position is required.");

    const url = isEditMode.value
        ? `/admin/provincial-officials/${form.id}`
        : "/admin/provincial-officials";

    const formattedData = new FormData();
    formattedData.append("name", form.name);
    formattedData.append("position", form.position);
    if (form.profile_image) formattedData.append("profile_image", form.profile_image);

    router.post(url, formattedData, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: ({ props }) => {
            officialsList.value = props.officials;
            showSuccessMessage(
                isEditMode.value
                    ? "Official updated successfully!"
                    : "Official added successfully!"
            );
            closeModal();
        },
        onError: (errors) => {
            errorMessage.value =
                Object.values(errors).flat().join(", ") || "An error occurred.";
        },
    });
};

const openDeleteModal = (official) => {
    officialToDelete.value = official;
    isDeleteModalOpen.value = true;
};

const closeDeleteModal = () => {
    officialToDelete.value = null;
    isDeleteModalOpen.value = false;
};

const deleteOfficial = async () => {
    if (!officialToDelete.value) return;

    try {
        await form.delete(`/admin/provincial-officials/${officialToDelete.value.id}`);
        officialsList.value = officialsList.value.filter(
            (o) => o.id !== officialToDelete.value.id
        );
        showSuccessMessage("Official deleted successfully!");
        closeDeleteModal();
    } catch (error) {
        errorMessage.value = "Failed to delete Official.";
    }
};
</script>

<template>
    <div class="p-4">
        <h1
            class="text-3xl md:text-4xl mb-5 font-bold text-gray-800 border-b-2 border-gray-500 pb-2 text-center mx-auto w-fit">
            PROVINCIAL OFFICIALS
        </h1>

        <transition name="fade">
            <div v-if="showSuccess"
                class="fixed top-20 right-5 bg-green-500 text-white p-3 rounded shadow-lg z-50 flex items-center gap-2">
                <i class="fas fa-check-circle text-white text-lg"></i>
                <span>{{ successMessage }}</span>
            </div>
        </transition>

        <div class="flex flex-col md:flex-row md:items-center gap-2 mb-4">
            <button @click="openModal()"
                class="bg-blue-800 text-white px-4 py-2 rounded flex items-center gap-2 hover:bg-blue-900 w-full md:w-auto">
                <i class="fas fa-plus-circle"></i>
                Add Official
            </button>

            <div class="relative flex-1 w-full md:w-auto">
                <input v-model="filters.search" @keyup.enter="fetchOfficials" type="text"
                    placeholder="Search Officials..." class="border p-2 pl-4 pr-12 rounded w-full" />
                <div class="absolute right-1 top-1/2 transform -translate-y-1/2 text-gray-700 px-3 py-1 rounded">
                    <i class="fas fa-search"></i>
                </div>
            </div>

            <select v-model="filters.position" class="border p-2 rounded w-full md:w-auto">
                <option value="">All Positions</option>
                <option v-for="position in positions" :key="position" :value="position">
                    {{ position }}
                </option>
            </select>
        </div>

        <div class="overflow-x-auto hidden xl:block">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-gray-200 text-gray-700 text-sm uppercase tracking-wider">
                        <th class="p-3 text-left w-[25%]">Profile Image</th>
                        <th class="p-3 text-left w-[25%]">Name</th>
                        <th class="p-3 text-left w-[25%]">Position</th>
                        <th class="p-3 text-center w-[25%]">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="official in officialsList" :key="official.id"
                        class="border-b hover:bg-gray-50 transition">
                        <td class="p-3 text-gray-600 break-words flex justify-center">
                            <img v-if="official.profile_image" loading="lazy" :src="`/provincial_officials/${official.profile_image}`"
                                class="w-20 h-20 rounded-full object-cover" alt="Profile Image" />
                            <span v-else>No Image</span>
                        </td>
                        <td class="p-3 text-gray-900 font-extrabold break-words">
                            {{ official.name }}
                        </td>
                        <td class="p-3 text-gray-600 break-words">
                            {{ official.position }}
                        </td>

                        <td class="p-3 text-center">
                            <div class="flex justify-center gap-1">
                                <button @click="openModal(official)"
                                    class="bg-blue-800 hover:bg-blue-900 text-white px-3 py-1 rounded text-sm transition">
                                    View | Edit
                                </button>
                                <button @click="openDeleteModal(official)"
                                    class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm transition">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="block xl:hidden space-y-4">
            <div v-for="official in officialsList" :key="official.id"
                class="border rounded-lg shadow-md bg-gray-100 p-4">
                <div class="flex justify-center mb-4">
                    <div class="w-32 h-32 rounded-full overflow-hidden border-4 border-white shadow-lg">
                        <img v-if="official.profile_image" loading="lazy" :src="`/provincial_officials/${official.profile_image}`"
                            class="w-full h-full object-cover" alt="Profile Image" />
                        <div v-else class="w-full h-full bg-gray-300 flex items-center justify-center text-gray-600">
                            No Image
                        </div>
                    </div>
                </div>

                <div class="text-center">
                    <h2 class="text-xl font-extrabold text-gray-900 mb-2">
                        {{ official.name }}
                    </h2>
                    <p class="text-sm text-gray-600">
                        <span class="font-bold">Position:</span> {{ official.position }}
                    </p>
                </div>

                <div class="mt-4 flex gap-2">
                    <button @click="openModal(official)"
                        class="flex-1 bg-blue-800 hover:bg-blue-900 text-white text-sm px-3 py-2 rounded transition">
                        <i class="fas fa-edit"></i> View | Edit
                    </button>
                    <button @click="openDeleteModal(official)"
                        class="flex-1 bg-red-600 hover:bg-red-700 text-white text-sm px-3 py-2 rounded transition">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
        </div>

        <div v-if="isModalOpen" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex justify-center items-center px-2 py-4">
            <div class="bg-white p-2 lg:p-6 shadow-lg w-full max-w-lg max-h-[95vh] overflow-y-auto">
                <h2 class="text-xl mb-4 font-extrabold">
                    {{ isEditMode ? "Edit Official" : "Add Official" }}
                </h2>

                <div v-if="errorMessage" class="bg-red-500 text-white p-2 rounded mb-2 flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    {{ errorMessage }}
                </div>

                <label class="font-bold block text-gray-700">Name</label>
                <input v-model="form.name" placeholder="Enter Name" class="border p-2 w-full my-2" />

                <label class="font-bold block text-gray-700">Position</label>
                <select v-model="form.position" class="border p-2 w-full my-2">
                    <option value="" disabled>Select Position</option>
                    <option v-for="position in positions" :key="position" :value="position">
                        {{ position }}
                    </option>
                </select>

                <label class="font-bold block text-gray-700">Profile Image</label>
                <input type="file" @change="form.profile_image = $event.target.files[0]" accept="image/*"
                    class="border p-2 w-full my-2" />

                <div class="flex justify-end gap-2 mt-4">
                    <button @click="submitOfficial"
                        class="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600 flex items-center gap-1">
                        <i class="fas fa-save"></i> Save
                    </button>
                    <button @click="closeModal" class="px-2 py-1 bg-gray-400 rounded hover:bg-gray-500">
                        Cancel
                    </button>
                </div>
            </div>
        </div>

        <div v-if="isDeleteModalOpen"
            class="fixed inset-0 bg-gray-900 bg-opacity-50 flex justify-center items-center px-2 py-4">
            <div class="bg-white p-2 lg:p-6 rounded shadow-lg w-full max-w-lg">
                <h2 class="text-xl mb-4 text-center">Are you sure?</h2>
                <p class="text-center mb-4">
                    Do you really want to delete
                    <strong>{{ officialToDelete?.name }}</strong>?
                </p>

                <div class="flex justify-center gap-2">
                    <button @click="deleteOfficial" class="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600">
                        Yes, Delete
                    </button>
                    <button @click="closeDeleteModal" class="px-2 py-1 bg-gray-400 rounded hover:bg-gray-500">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.5s;
}

.fade-enter,
.fade-leave-to {
    opacity: 0;
}
</style>
