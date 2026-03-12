import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";

export default function Dashboard() {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                    Admin Dashboard
                </h2>
            }
        >
            <Head title="Admin Dashboard" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <h3 className="text-lg font-semibold mb-4">
                                Welcome to Admin Panel
                            </h3>
                            <p className="text-gray-600 mb-4">
                                Manage your application from here.
                            </p>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                <a
                                    href={route("admin.users.index")}
                                    className="p-4 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition"
                                >
                                    <h4 className="font-semibold text-blue-900">
                                        Manage Users
                                    </h4>
                                    <p className="text-sm text-blue-700">
                                        View and manage all users
                                    </p>
                                </a>

                                <a
                                    href={route("admin.reset-password")}
                                    className="p-4 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition"
                                >
                                    <h4 className="font-semibold text-red-900">
                                        Reset Password
                                    </h4>
                                    <p className="text-sm text-red-700">
                                        Force reset user passwords
                                    </p>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
