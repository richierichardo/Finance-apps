import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";

export default function Show({ userId }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                    User Details
                </h2>
            }
        >
            <Head title="User Details" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <h3 className="text-lg font-semibold mb-4">
                                User #{userId}
                            </h3>
                            <p className="text-gray-600">
                                Placeholder for user details view.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
